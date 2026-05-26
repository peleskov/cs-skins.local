<?php

namespace App\Services;

use App\Jobs\BroadcastCaseDropJob;
use App\Models\BonusTransaction;
use App\Models\CaseInventoryItem;
use App\Models\CaseModel;
use App\Models\CaseOpen;
use App\Models\CaseTier;
use App\Models\CaseItem;
use App\Models\Client;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CaseService
{
    /**
     * Открытие кейса и разыгрыш приза (с поддержкой множественного открытия)
     *
     * @return array{items: array, payment: array}
     */
    public function openCase(CaseModel $case, Client $client, int $count = 1): array
    {
        return DB::transaction(function () use ($case, $client, $count) {
            // Блокируем кейс для предотвращения race conditions
            $case = CaseModel::lockForUpdate()->find($case->id);

            // 1. Проверить доступность кейса
            if (!$case->isAvailable()) {
                throw new Exception('Кейс недоступен');
            }

            // 2. Определить тип открытия и списать средства (за все открытия)
            $payment = $this->processPayment($case, $client, $count);

            $isRiggedClient = $client->isRiggingActive();

            // 3. Пополнить фонд кейса (если платное открытие и не подкрутка)
            if (!$payment['is_free'] && !$isRiggedClient) {
                $fundAmount = $payment['price_per_case'] * ($case->fund_percent / 100) * $count;
                $case->increment('accumulated_fund', $fundAmount);
            }

            // 4. Если лимитированный — увеличить счётчик (для подкрутки не учитываем)
            if ($case->isLimited() && !$isRiggedClient) {
                $case->increment('total_opens_count', $count);
            }

            $items = [];
            $caseInventoryService = app(CaseInventoryService::class);
            $isRigged = $client->isRiggingActive();

            // 5. Открываем $count раз
            for ($i = 0; $i < $count; $i++) {
                // Подкрутка (6.8) — обход стандартной экономики
                if ($isRigged) {
                    $caseItem = $this->selectRiggedItem($case, $client);
                    if (! $caseItem) {
                        // Конфигурация подкрутки невалидна (нет пресетов / сумма ≠ 100% / нет предметов)
                        // Откатываем оплату и сообщаем
                        throw new Exception('Подкрутка настроена некорректно — обратитесь к администратору');
                    }

                    $inventoryItem = $caseInventoryService->addItem(
                        $client,
                        $caseItem->virtualItem,
                        $caseItem->price,
                        CaseInventoryItem::SOURCE_CASE,
                        $case->id
                    );

                    $caseOpen = CaseOpen::create([
                        'client_id' => $client->id,
                        'case_id' => $case->id,
                        'case_inventory_item_id' => $inventoryItem->id,
                        'price_paid' => $payment['is_free'] ? 0 : $payment['price_per_case'],
                        'balance_used' => $payment['is_free'] ? 0 : ($payment['balance_used'] / $count),
                        'bonus_balance_used' => $payment['is_free'] ? 0 : ($payment['bonus_used'] / $count),
                        'is_free' => $payment['is_free'],
                        'is_anti_unluck' => false,
                    ]);

                    $items[] = [
                        'case_item' => $caseItem,
                        'inventory_item' => $inventoryItem,
                        'case_open' => $caseOpen,
                        'is_anti_unluck' => false,
                    ];

                    BroadcastCaseDropJob::dispatch($caseOpen->id)
                        ->delay(now()->addSeconds((int) SiteSetting::get('case_feed_broadcast_delay', 7)));

                    continue;
                }

                // Проверяем анти-анлак перед каждым открытием
                $isAntiUnluck = !$payment['is_free'] && $this->checkAntiUnluck($client, $case);

                if ($isAntiUnluck) {
                    // Возвращаем стоимость одного открытия на баланс
                    $this->refundOneOpen($client, $payment, $count);
                }

                // Получить доступные тиры (с учётом текущего фонда)
                $availableTiers = $this->getAvailableTiers($case);

                if ($availableTiers->isEmpty()) {
                    throw new Exception('Нет доступных уровней для розыгрыша');
                }

                // Выбрать уровень по вероятности
                $tier = $this->selectTierByProbability($availableTiers);

                // Выбрать случайный предмет из уровня (БЕЗ удаления)
                $caseItem = $this->selectItemFromTier($tier);

                // Вычесть стоимость приза из фонда
                $prizePrice = (float) $caseItem->price;
                if ($case->accumulated_fund >= $prizePrice) {
                    $case->decrement('accumulated_fund', $prizePrice);
                }

                $isFree = $payment['is_free'] || $isAntiUnluck;

                // Создать запись в инвентаре
                $inventoryItem = $caseInventoryService->addItem(
                    $client,
                    $caseItem->virtualItem,
                    $caseItem->price,
                    CaseInventoryItem::SOURCE_CASE,
                    $case->id
                );

                // Создать запись в истории
                // Помечаем предмет инвентаря как анти-анлак
                if ($isAntiUnluck) {
                    $inventoryItem->update(['is_anti_unluck' => true]);
                }

                $caseOpen = CaseOpen::create([
                    'client_id' => $client->id,
                    'case_id' => $case->id,
                    'case_inventory_item_id' => $inventoryItem->id,
                    'price_paid' => $isFree ? 0 : $payment['price_per_case'],
                    'balance_used' => $isFree ? 0 : ($payment['balance_used'] / $count),
                    'bonus_balance_used' => $isFree ? 0 : ($payment['bonus_used'] / $count),
                    'is_free' => $isFree,
                    'is_anti_unluck' => $isAntiUnluck,
                ]);

                $items[] = [
                    'case_item' => $caseItem,
                    'inventory_item' => $inventoryItem,
                    'case_open' => $caseOpen,
                    'is_anti_unluck' => $isAntiUnluck,
                ];

                // Отправляем событие в лайв-ленту с задержкой,
                // чтобы лента не спойлерила выпадение до окончания анимации рулетки.
                BroadcastCaseDropJob::dispatch($caseOpen->id)
                    ->delay(now()->addSeconds((int) SiteSetting::get('case_feed_broadcast_delay', 7)));

                // Логируем операцию
                Log::info('Case opened', [
                    'case_id' => $case->id,
                    'client_id' => $client->id,
                    'tier_id' => $tier->id,
                    'case_item_id' => $caseItem->id,
                    'prize_price' => $prizePrice,
                    'is_free' => $isFree,
                    'is_anti_unluck' => $isAntiUnluck,
                    'open_number' => $i + 1,
                    'total_opens' => $count,
                ]);
            }

            // 6. Создать транзакции (одна на всю сумму)
            $this->createTransactions($case, $client, $payment, $items);

            return [
                'items' => $items,
                'payment' => $payment,
            ];
        });
    }

    /**
     * Обработка оплаты за открытие кейса
     */
    private function processPayment(CaseModel $case, Client $client, int $count): array
    {
        $pricePerCase = $this->getCasePrice($case, $client);
        $totalPrice = $pricePerCase * $count;

        // Бесплатный кейс
        if ($case->isFree()) {
            $freeCaseService = app(FreeCaseService::class);
            $availableFree = $freeCaseService->getAvailableFreeOpens($client, $case);

            if ($availableFree >= $count) {
                return [
                    'total' => 0,
                    'balance_used' => 0,
                    'bonus_used' => 0,
                    'is_free' => true,
                    'count' => $count,
                    'price_per_case' => 0,
                ];
            }

            // Если не хватает бесплатных — ошибка (не смешиваем бесплатные с платными)
            throw new Exception("Доступно только {$availableFree} бесплатных открытий");
        }

        // Платное открытие: сначала бонусный, потом основной
        $result = $client->debitWithBonusPriority($totalPrice);

        if (!$result['success']) {
            throw new Exception('Недостаточно средств');
        }

        return [
            'total' => $totalPrice,
            'balance_used' => $result['balance_used'],
            'bonus_used' => $result['bonus_used'],
            'is_free' => false,
            'count' => $count,
            'price_per_case' => $pricePerCase,
        ];
    }

    /**
     * Создать транзакции для открытия кейса
     */
    private function createTransactions(CaseModel $case, Client $client, array $payment, array $items): void
    {
        // Если бесплатное открытие - транзакций не создаём
        if ($payment['is_free']) {
            return;
        }

        // Считаем анти-анлак открытия для корректировки суммы
        $antiUnluckCount = collect($items)->filter(fn($item) => $item['is_anti_unluck'] ?? false)->count();
        $paidCount = $payment['count'] - $antiUnluckCount;

        if ($paidCount <= 0) {
            return;
        }

        $paidRatio = $paidCount / $payment['count'];
        $actualBalanceUsed = round($payment['balance_used'] * $paidRatio, 2);
        $actualBonusUsed = round($payment['bonus_used'] * $paidRatio, 2);
        $actualTotal = round($payment['total'] * $paidRatio, 2);

        // Собираем ID всех полученных предметов инвентаря
        $inventoryItemIds = array_map(fn($item) => $item['inventory_item']->id, $items);

        $countLabel = $paidCount > 1 ? " x{$paidCount}" : '';
        $antiUnluckLabel = $antiUnluckCount > 0 ? " ({$antiUnluckCount} бесплатно по анти-анлак)" : '';

        // Транзакция основного баланса (покупка кейса)
        if ($actualBalanceUsed > 0) {
            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_CASE_PURCHASE,
                'amount' => $actualBalanceUsed,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Открытие кейса \"{$case->name}\"{$countLabel}{$antiUnluckLabel}",
                'metadata' => [
                    'case_id' => $case->id,
                    'case_inventory_item_ids' => $inventoryItemIds,
                    'count' => $paidCount,
                    'anti_unluck_count' => $antiUnluckCount,
                ],
            ]);
        }

        // Транзакция бонусного баланса
        if ($actualBonusUsed > 0) {
            BonusTransaction::create([
                'client_id' => $client->id,
                'type' => BonusTransaction::TYPE_DEBIT,
                'amount' => $actualBonusUsed,
                'description' => "Открытие кейса \"{$case->name}\"{$countLabel}{$antiUnluckLabel}",
                'case_id' => $case->id,
            ]);
        }

        // Транзакция дохода сайта (для аналитики)
        if ($actualTotal > 0) {
            $fundAmount = $payment['price_per_case'] * ($case->fund_percent / 100) * $paidCount;
            $siteRevenue = $actualTotal - $fundAmount;

            if ($siteRevenue > 0) {
                $systemClient = Client::where('email', 'system@cs-skins.local')->first();

                Transaction::create([
                    'client_id' => $systemClient?->id,
                    'type' => Transaction::TYPE_FEE,
                    'amount' => $siteRevenue,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => "Доход с кейса \"{$case->name}\"{$countLabel}",
                    'metadata' => [
                        'case_id' => $case->id,
                        'buyer_id' => $client->id,
                        'fund_percent' => $case->fund_percent,
                        'source' => 'case_revenue',
                        'count' => $paidCount,
                    ],
                ]);
            }
        }
    }

    /**
     * Получить доступные уровни на основе фонда кейса
     */
    private function getAvailableTiers(CaseModel $case): \Illuminate\Database\Eloquent\Collection
    {
        return $case->tiers()
            ->where(function ($query) use ($case) {
                // Уровень доступен если фонд >= 2 * цена уровня
                // ИЛИ это самый дешевый уровень (всегда доступен)
                $query->where('price', '<=', $case->accumulated_fund / 2)
                      ->orWhere('price', '=', function ($subQuery) use ($case) {
                          $subQuery->selectRaw('MIN(price)')
                                   ->from('case_tiers')
                                   ->where('case_id', $case->id);
                      });
            })
            ->whereHas('items') // Только уровни с предметами
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Выбрать уровень по вероятности
     */
    private function selectTierByProbability(\Illuminate\Database\Eloquent\Collection $tiers): CaseTier
    {
        $totalProbability = $tiers->sum('probability');
        $random = mt_rand(1, (int) ($totalProbability * 100)) / 100;

        $cumulative = 0;
        foreach ($tiers as $tier) {
            $cumulative += $tier->probability;
            if ($random <= $cumulative) {
                return $tier;
            }
        }

        // Fallback - возвращаем самый дешевый уровень
        return $tiers->first();
    }

    /**
     * Выбрать случайный предмет из уровня (БЕЗ удаления из кейса)
     */
    private function selectItemFromTier(CaseTier $tier): CaseItem
    {
        $caseItems = $tier->items()->with('virtualItem')->get();

        if ($caseItems->isEmpty()) {
            throw new Exception("Нет предметов в уровне {$tier->name}");
        }

        // Выбираем случайный, НЕ удаляем
        return $caseItems->random();
    }

    /**
     * Получить максимальное количество открытий за раз
     */
    public function getMaxOpens(CaseModel $case, Client $client): int
    {
        $maxMultiplier = 10; // Максимальный множитель

        // Бесплатный кейс — ограничен доступными бесплатными открытиями
        if ($case->isFree()) {
            $freeCaseService = app(FreeCaseService::class);
            $freeOpens = $freeCaseService->getAvailableFreeOpens($client, $case);
            return min($freeOpens, $maxMultiplier);
        }

        // Лимитированный кейс — ограничен оставшимися открытиями
        if ($case->isLimited() && $case->total_opens_limit !== null) {
            $remaining = $case->total_opens_limit - $case->total_opens_count;
            return min(max(0, $remaining), $maxMultiplier);
        }

        // Обычный кейс — ограничен балансом
        $totalBalance = (float) $client->balance + (float) $client->bonus_balance;
        $price = (float) $this->getCasePrice($case, $client);

        if ($price <= 0) {
            return $maxMultiplier;
        }

        $canAfford = (int) floor($totalBalance / $price);

        return min(max(0, $canAfford), $maxMultiplier);
    }

    /**
     * Получить доступные множители для открытия
     */
    public function getAvailableMultipliers(CaseModel $case, Client $client): array
    {
        $allMultipliers = [1, 2, 3, 4, 5, 10];
        $maxOpens = $this->getMaxOpens($case, $client);

        return array_values(array_filter($allMultipliers, fn($m) => $m <= $maxOpens));
    }

    /**
     * Проверить может ли пользователь купить кейс
     */
    public function canPurchaseCase(CaseModel $case, Client $client): bool
    {
        // Проверяем что кейс доступен
        if (!$case->isAvailable()) {
            return false;
        }

        // Проверяем что у кейса есть уровни с предметами
        if (!$case->tiers()->whereHas('items')->exists()) {
            return false;
        }

        // Для бесплатного кейса - проверяем доступность бесплатных открытий
        if ($case->isFree()) {
            $freeCaseService = app(FreeCaseService::class);
            return $freeCaseService->canOpenFree($client, $case);
        }

        // Для платного - проверяем общий баланс
        $totalBalance = (float) $client->balance + (float) $client->bonus_balance;
        return $totalBalance >= $this->getCasePrice($case, $client);
    }

    /**
     * Проверить анти-анлак: 10 последних открытий — один кейс, все неокуп, ни одно не бесплатное
     */
    /**
     * Подкрутка: выбираем предмет из кейса по пресетам клиента.
     * Возвращает null если конфигурация невалидна (нет пресетов / сумма ≠ 100%).
     */
    private function selectRiggedItem(CaseModel $case, Client $client): ?CaseItem
    {
        $presets = $client->riggingPresets;
        if ($presets->isEmpty()) {
            return null;
        }

        $sumChance = $presets->sum(fn ($p) => (float) $p->chance_percent);
        if (abs(round($sumChance, 2) - 100) > 0.001) {
            return null;
        }

        // Взвешенный random по chance_percent
        $roll = mt_rand(1, 10000) / 100; // 0.01..100
        $cumulative = 0;
        $selectedPreset = null;
        foreach ($presets as $preset) {
            $cumulative += (float) $preset->chance_percent;
            if ($roll <= $cumulative) {
                $selectedPreset = $preset;
                break;
            }
        }
        if (! $selectedPreset) {
            $selectedPreset = $presets->last();
        }

        $targetPrice = (float) $case->price * (float) $selectedPreset->price_percent / 100;

        // Берём предмет кейса с ценой ближайшей к target
        $caseItems = $case->items()->with('virtualItem')->get();
        if ($caseItems->isEmpty()) {
            return null;
        }

        return $caseItems->sortBy(fn ($item) => abs((float) $item->price - $targetPrice))->first();
    }

    private function checkAntiUnluck(Client $client, CaseModel $case): bool
    {
        if (!$client->premiumFeatureEnabled('anti_unluck')) {
            return false;
        }

        $threshold = (int) SiteSetting::get('anti_unluck_threshold', 10);

        $lastOpens = CaseOpen::where('client_id', $client->id)
            ->latest('id')
            ->limit($threshold)
            ->with('inventoryItem')
            ->get();

        if ($lastOpens->count() < $threshold) {
            return false;
        }

        foreach ($lastOpens as $open) {
            // Все должны быть тот же кейс
            if ($open->case_id !== $case->id) {
                return false;
            }
            // Ни одно не бесплатное
            if ($open->is_free) {
                return false;
            }
            // Все неокуп: цена предмета < цена открытия
            if (!$open->inventoryItem || (float) $open->inventoryItem->price >= (float) $open->price_paid) {
                return false;
            }
        }

        return true;
    }

    /**
     * Вернуть стоимость одного открытия на баланс (анти-анлак)
     */
    private function refundOneOpen(Client $client, array $payment, int $count): void
    {
        $balancePerOpen = $payment['balance_used'] / $count;
        $bonusPerOpen = $payment['bonus_used'] / $count;

        if ($balancePerOpen > 0) {
            $client->increment('balance', $balancePerOpen);
        }
        if ($bonusPerOpen > 0) {
            $client->increment('bonus_balance', $bonusPerOpen);
        }
    }

    /**
     * Получить цену кейса с учётом PREMIUM-скидки
     */
    public function getCasePrice(CaseModel $case, Client $client): float
    {
        $price = (float) $case->price;

        if (!$client->premiumFeatureEnabled('case_discount')) {
            return $price;
        }

        $threshold = (float) SiteSetting::get('premium_case_discount_threshold', 500);
        $discountPercent = $price <= $threshold
            ? (float) SiteSetting::get('premium_case_discount_low', 10)
            : (float) SiteSetting::get('premium_case_discount_high', 5);

        return round($price * (1 - $discountPercent / 100), 2);
    }
}
