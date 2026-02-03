<?php

namespace App\Services;

use App\Events\CaseDropEvent;
use App\Models\BonusTransaction;
use App\Models\CaseInventoryItem;
use App\Models\CaseModel;
use App\Models\CaseOpen;
use App\Models\CaseTier;
use App\Models\CaseItem;
use App\Models\Client;
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

            // 3. Пополнить фонд кейса (если платное открытие)
            if (!$payment['is_free']) {
                $fundAmount = $case->price * ($case->fund_percent / 100) * $count;
                $case->increment('accumulated_fund', $fundAmount);
            }

            // 4. Если лимитированный — увеличить счётчик
            if ($case->isLimited()) {
                $case->increment('total_opens_count', $count);
            }

            $items = [];
            $caseInventoryService = app(CaseInventoryService::class);

            // 5. Открываем $count раз
            for ($i = 0; $i < $count; $i++) {
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

                // Создать запись в инвентаре
                $inventoryItem = $caseInventoryService->addItem(
                    $client,
                    $caseItem->virtualItem,
                    $caseItem->price,
                    CaseInventoryItem::SOURCE_CASE,
                    $case->id
                );

                // Создать запись в истории
                $caseOpen = CaseOpen::create([
                    'client_id' => $client->id,
                    'case_id' => $case->id,
                    'case_inventory_item_id' => $inventoryItem->id,
                    'price_paid' => $payment['is_free'] ? 0 : $case->price,
                    'balance_used' => $payment['is_free'] ? 0 : ($payment['balance_used'] / $count),
                    'bonus_balance_used' => $payment['is_free'] ? 0 : ($payment['bonus_used'] / $count),
                    'is_free' => $payment['is_free'],
                ]);

                $items[] = [
                    'case_item' => $caseItem,
                    'inventory_item' => $inventoryItem,
                    'case_open' => $caseOpen,
                ];

                // Отправляем событие в лайв-ленту (afterCommit задан в самом событии)
                broadcast(new CaseDropEvent($caseOpen));

                // Логируем операцию
                Log::info('Case opened', [
                    'case_id' => $case->id,
                    'client_id' => $client->id,
                    'tier_id' => $tier->id,
                    'case_item_id' => $caseItem->id,
                    'prize_price' => $prizePrice,
                    'is_free' => $payment['is_free'],
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
        $totalPrice = $case->price * $count;

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

        // Собираем ID всех полученных предметов инвентаря
        $inventoryItemIds = array_map(fn($item) => $item['inventory_item']->id, $items);

        // Транзакция основного баланса (покупка кейса)
        if ($payment['balance_used'] > 0) {
            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_CASE_PURCHASE,
                'amount' => $payment['balance_used'],
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Открытие кейса \"{$case->name}\"" . ($payment['count'] > 1 ? " x{$payment['count']}" : ''),
                'metadata' => [
                    'case_id' => $case->id,
                    'case_inventory_item_ids' => $inventoryItemIds,
                    'count' => $payment['count'],
                ],
            ]);
        }

        // Транзакция бонусного баланса
        if ($payment['bonus_used'] > 0) {
            BonusTransaction::create([
                'client_id' => $client->id,
                'type' => BonusTransaction::TYPE_DEBIT,
                'amount' => $payment['bonus_used'],
                'description' => "Открытие кейса \"{$case->name}\"" . ($payment['count'] > 1 ? " x{$payment['count']}" : ''),
                'case_id' => $case->id,
            ]);
        }

        // Транзакция дохода сайта (для аналитики)
        if ($payment['total'] > 0) {
            $fundAmount = $case->price * ($case->fund_percent / 100) * $payment['count'];
            $siteRevenue = $payment['total'] - $fundAmount;

            if ($siteRevenue > 0) {
                $systemClient = Client::where('email', 'system@cs-skins.local')->first();

                Transaction::create([
                    'client_id' => $systemClient?->id,
                    'type' => Transaction::TYPE_FEE,
                    'amount' => $siteRevenue,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => "Доход с кейса \"{$case->name}\"" . ($payment['count'] > 1 ? " x{$payment['count']}" : ''),
                    'metadata' => [
                        'case_id' => $case->id,
                        'buyer_id' => $client->id,
                        'fund_percent' => $case->fund_percent,
                        'source' => 'case_revenue',
                        'count' => $payment['count'],
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
        $price = (float) $case->price;

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
        return $totalBalance >= $case->price;
    }
}
