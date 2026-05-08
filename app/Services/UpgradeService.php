<?php

namespace App\Services;

use App\Models\CaseInventoryItem;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\Upgrade;
use App\Models\VirtualItem;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;

class UpgradeService
{
    /**
     * Получить настройки апгрейда
     */
    public function getSettings(): array
    {
        return [
            'min_chance' => (float) SiteSetting::get('upgrade_min_chance', 1),
            'max_chance' => (float) SiteSetting::get('upgrade_max_chance', 70),
            'commission' => (float) SiteSetting::get('upgrade_commission', 15),
            'max_items' => 4,
        ];
    }

    /**
     * Рассчитать шанс выигрыша
     * Формула: (сумма_ставки / цена_желаемого) * (100 - комиссия)
     */
    public function calculateChance(float $betTotal, float $targetPrice): float
    {
        $settings = $this->getSettings();
        $commission = $settings['commission'];

        $chance = ($betTotal / $targetPrice) * (100 - $commission);

        // Ограничения
        return max($settings['min_chance'], min($settings['max_chance'], $chance));
    }

    /**
     * Получить диапазон цен для целевых предметов
     */
    public function getPriceRange(float $betTotal): array
    {
        $settings = $this->getSettings();
        $multiplier = (100 - $settings['commission']) / 100;

        // При макс шансе - минимальная цена цели
        $minPrice = $betTotal * $multiplier / ($settings['max_chance'] / 100);
        // При мин шансе - максимальная цена цели
        $maxPrice = $betTotal * $multiplier / ($settings['min_chance'] / 100);

        return [
            'min' => round($minPrice, 2),
            'max' => round($maxPrice, 2),
        ];
    }

    /**
     * Получить доступные целевые предметы
     */
    public function getAvailableTargets(float $betTotal, array $filters = [], int $limit = 50): array
    {
        $settings = $this->getSettings();
        $usdRate = (float) SiteSetting::get('usd_course', 100);

        // Конвертируем ставку в USD (steam_price в USD)
        $betTotalUsd = $betTotal / $usdRate;
        $multiplier = (100 - $settings['commission']) / 100;

        // Диапазон цен в USD
        $minPriceUsd = $betTotalUsd * $multiplier / ($settings['max_chance'] / 100);
        $maxPriceUsd = $betTotalUsd * $multiplier / ($settings['min_chance'] / 100);

        // Минимальная цена предмета 10 рублей
        $minPriceFloorUsd = 10 / $usdRate;
        $minPriceUsd = max($minPriceUsd, $minPriceFloorUsd);

        // Фильтры по шансу (кнопки на фронтенде)
        if (!empty($filters['chance_max'])) {
            $chanceMax = (float) $filters['chance_max'];
            if ($chanceMax > 0) {
                // Меньший шанс = дороже предмет, поэтому chance_max задаёт нижнюю границу цены
                $minPriceUsd = max($minPriceUsd, $betTotalUsd * $multiplier / ($chanceMax / 100));
            }
        }
        if (!empty($filters['chance_min'])) {
            $chanceMin = (float) $filters['chance_min'];
            if ($chanceMin > 0) {
                // Больший шанс = дешевле предмет, поэтому chance_min задаёт верхнюю границу цены
                $maxPriceUsd = min($maxPriceUsd, $betTotalUsd * $multiplier / ($chanceMin / 100));
            }
        }

        // Применяем пользовательские фильтры цены (в рублях -> USD)
        if (!empty($filters['price_from'])) {
            $minPriceUsd = max($minPriceUsd, (float) $filters['price_from'] / $usdRate);
        }
        if (!empty($filters['price_to'])) {
            $maxPriceUsd = min($maxPriceUsd, (float) $filters['price_to'] / $usdRate);
        }

        $mode = SiteSetting::get('upgrade_target_mode', 'virtual');

        if ($mode === 'market') {
            return $this->getMarketTargets(
                $betTotal,
                $multiplier,
                $usdRate,
                $minPriceUsd * $usdRate,
                $maxPriceUsd * $usdRate,
                $filters,
                $limit
            );
        }

        $query = VirtualItem::whereBetween('steam_price', [$minPriceUsd, $maxPriceUsd])
            ->where('steam_price', '>=', $minPriceFloorUsd);

        // Фильтр по поиску
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('market_hash_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('steam_price')
            ->limit($limit)
            ->get()
            ->map(function ($item) use ($betTotal, $multiplier, $usdRate) {
                $priceRub = $item->steam_price * $usdRate;
                $chance = ($betTotal / $priceRub) * $multiplier * 100;
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => round($priceRub, 2),
                    'image_url' => $item->image_url,
                    'rarity' => $item->rarity,
                    'quality' => $item->quality,
                    'weapon_type' => $item->weapon_type,
                    'chance' => round($chance, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Получить пул целей из активных листингов маркетплейса.
     * Группируем по market_hash_name, берём минимальную цену.
     */
    protected function getMarketTargets(
        float $betTotal,
        float $multiplier,
        float $usdRate,
        float $minPriceRub,
        float $maxPriceRub,
        array $filters,
        int $limit
    ): array {
        $rows = DB::table('listings')
            ->select('market_hash_name', DB::raw('MIN(price) as min_price'), DB::raw('MIN(id) as listing_id'))
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereIn('seller_id', $this->getOnlineSellerIds())
            ->whereBetween('price', [$minPriceRub, $maxPriceRub])
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($qq) use ($search) {
                    $qq->where('market_hash_name', 'like', "%{$search}%")
                        ->orWhere('inventory_item_name', 'like', "%{$search}%");
                });
            })
            ->groupBy('market_hash_name')
            ->orderBy('min_price')
            ->limit($limit)
            ->get();

        $listingIds = $rows->pluck('listing_id')->all();
        $listings = Listing::whereIn('id', $listingIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($listings, $betTotal, $multiplier) {
            $listing = $listings->get($row->listing_id);
            if (!$listing) {
                return null;
            }
            $priceRub = (float) $row->min_price;
            $chance = ($betTotal / $priceRub) * $multiplier * 100;
            return [
                'id' => $listing->id,
                'name' => $listing->inventory_item_name ?: $listing->market_hash_name,
                'price' => round($priceRub, 2),
                'image_url' => $listing->inventory_icon_url,
                'rarity' => null,
                'quality' => $listing->wear_condition,
                'weapon_type' => $this->extractWeaponType($listing->market_hash_name),
                'chance' => round($chance, 2),
            ];
        })->filter()->values()->toArray();
    }

    /**
     * ID онлайн-продавцов (тот же фильтр, что и на маркетплейсе).
     */
    protected function getOnlineSellerIds(): array
    {
        Redis::zremrangebyscore('online_sellers', '-inf', now()->timestamp);
        $ids = Redis::zrangebyscore('online_sellers', now()->timestamp, '+inf');

        return !empty($ids) ? $ids : [0];
    }

    protected function extractWeaponType(?string $marketHashName): ?string
    {
        if (!$marketHashName) {
            return null;
        }
        $clean = preg_replace('/^(StatTrak™ |Souvenir |★ )/u', '', $marketHashName);
        $parts = explode(' | ', $clean, 2);
        return $parts[0] ?? null;
    }

    /**
     * Найти или создать VirtualItem из активного листинга по target_id.
     * Используется при apply в режиме market.
     */
    protected function resolveMarketTarget(int $listingId): ?VirtualItem
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            return null;
        }

        $usdRate = (float) SiteSetting::get('usd_course', 100) ?: 1;
        $priceRub = (float) $listing->price;

        return VirtualItem::firstOrCreate(
            ['market_hash_name' => $listing->market_hash_name],
            [
                'name' => $listing->inventory_item_name ?: $listing->market_hash_name,
                'weapon_type' => $this->extractWeaponType($listing->market_hash_name),
                'skin_name' => null,
                'quality' => $listing->wear_condition,
                'rarity' => null,
                'rarity_color' => null,
                'image_url' => $listing->inventory_icon_url,
                'steam_class_id' => $listing->steam_class_id,
                'price' => $priceRub,
                'steam_price' => round($priceRub / $usdRate, 2),
                'is_stattrak' => (bool) $listing->is_stattrak,
                'is_souvenir' => (bool) $listing->is_souvenir,
                'is_active' => true,
            ]
        );
    }

    /**
     * Выполнить апгрейд
     *
     * @param Client $client
     * @param array $itemIds IDs предметов из case_inventory_items
     * @param float $balanceAmount Сумма с основного баланса
     * @param int $targetId ID целевого VirtualItem
     * @return Upgrade
     * @throws Exception
     */
    public function execute(Client $client, array $itemIds, float $balanceAmount, int $targetId): Upgrade
    {
        return DB::transaction(function () use ($client, $itemIds, $balanceAmount, $targetId) {
            $settings = $this->getSettings();
            $usdRate = (float) SiteSetting::get('usd_course', 100);

            // 1. Валидация предметов
            $betItems = [];
            $itemsTotal = 0;

            if (!empty($itemIds)) {
                if (count($itemIds) > $settings['max_items']) {
                    throw new Exception('Максимум ' . $settings['max_items'] . ' предмета');
                }

                $items = CaseInventoryItem::whereIn('id', $itemIds)
                    ->where('client_id', $client->id)
                    ->where('status', CaseInventoryItem::STATUS_AVAILABLE)
                    ->lockForUpdate()
                    ->get();

                if ($items->count() !== count($itemIds)) {
                    throw new Exception('Некоторые предметы недоступны');
                }

                foreach ($items as $item) {
                    $betItems[] = [
                        'item_id' => $item->id,
                        'price' => (float) $item->price,
                    ];
                    $itemsTotal += (float) $item->price;
                }
            }

            // 2. Валидация баланса (только основной баланс!)
            if ($balanceAmount > 0) {
                if ($balanceAmount > $client->balance) {
                    throw new Exception('Недостаточно средств на балансе');
                }
            }

            $totalBet = $itemsTotal + $balanceAmount;

            if ($totalBet <= 0) {
                throw new Exception('Ставка должна быть больше 0');
            }

            // 3. Валидация цели
            $mode = SiteSetting::get('upgrade_target_mode', 'virtual');
            $targetItem = $mode === 'market'
                ? $this->resolveMarketTarget($targetId)
                : VirtualItem::find($targetId);
            if (!$targetItem || $targetItem->steam_price <= 0) {
                throw new Exception('Целевой предмет не найден');
            }

            $targetPriceRub = $targetItem->steam_price * $usdRate;

            // 4. Валидация: цель должна быть дороже ставки (апгрейд — всегда вверх)
            $priceRange = $this->getPriceRange($totalBet);
            if ($targetPriceRub < $priceRange['min']) {
                throw new Exception('Цена цели слишком низкая для апгрейда');
            }
            if ($targetPriceRub > $priceRange['max']) {
                throw new Exception('Цена цели слишком высокая для апгрейда');
            }

            // 5. Расчет шанса
            $chance = $this->calculateChance($totalBet, $targetPriceRub);

            if ($chance < $settings['min_chance']) {
                throw new Exception('Шанс слишком низкий');
            }

            // 5. Списание баланса
            if ($balanceAmount > 0) {
                $debited = $client->debit($balanceAmount);
                if (!$debited) {
                    throw new Exception('Не удалось списать средства');
                }

                // Создаем транзакцию
                Transaction::create([
                    'client_id' => $client->id,
                    'type' => Transaction::TYPE_UPGRADE_BET,
                    'amount' => $balanceAmount,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => 'Ставка в апгрейде',
                ]);
            }

            // 6. Помечаем предметы как upgraded
            if (!empty($itemIds)) {
                CaseInventoryItem::whereIn('id', $itemIds)
                    ->update(['status' => CaseInventoryItem::STATUS_UPGRADED]);
            }

            // 7. Генерация результата
            $rollValue = mt_rand(0, 10000) / 100; // 0.00 - 100.00
            $isWin = $rollValue <= $chance;

            // 8. Создание записи апгрейда
            $upgrade = Upgrade::create([
                'client_id' => $client->id,
                'bet_items' => $betItems,
                'bet_balance' => $balanceAmount,
                'total_bet' => $totalBet,
                'target_virtual_item_id' => $targetId,
                'target_price' => $targetPriceRub,
                'win_chance' => $chance,
                'roll_value' => $rollValue,
                'result' => $isWin ? Upgrade::RESULT_WIN : Upgrade::RESULT_LOSE,
                'won_item_id' => null,
            ]);

            // 9. Обработка выигрыша
            if ($isWin) {
                $wonItem = $this->processWin($upgrade, $client, $targetItem, $targetPriceRub);
                $upgrade->update(['won_item_id' => $wonItem->id]);
            }

            return $upgrade->fresh(['targetVirtualItem', 'wonItem.virtualItem']);
        });
    }

    /**
     * Обработка выигрыша - добавление предмета в инвентарь
     */
    protected function processWin(Upgrade $upgrade, Client $client, VirtualItem $targetItem, float $priceRub): CaseInventoryItem
    {
        return CaseInventoryItem::create([
            'client_id' => $client->id,
            'virtual_item_id' => $targetItem->id,
            'price' => $priceRub,
            'source_type' => CaseInventoryItem::SOURCE_UPGRADE,
            'source_id' => $upgrade->id,
            'status' => CaseInventoryItem::STATUS_AVAILABLE,
        ]);
    }
}
