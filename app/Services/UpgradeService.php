<?php

namespace App\Services;

use App\Models\CaseInventoryItem;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Upgrade;
use App\Models\VirtualItem;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
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
    public function getAvailableTargets(float $betTotal, int $limit = 50): array
    {
        $settings = $this->getSettings();
        $usdRate = (float) SiteSetting::get('usd_course', 100);

        // Конвертируем ставку в USD (steam_price в USD)
        $betTotalUsd = $betTotal / $usdRate;
        $multiplier = (100 - $settings['commission']) / 100;

        // Диапазон цен в USD
        $minPriceUsd = $betTotalUsd * $multiplier / ($settings['max_chance'] / 100);
        $maxPriceUsd = $betTotalUsd * $multiplier / ($settings['min_chance'] / 100);

        return VirtualItem::whereBetween('steam_price', [$minPriceUsd, $maxPriceUsd])
            ->where('steam_price', '>', 0)
            ->orderBy('steam_price')
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
            $targetItem = VirtualItem::find($targetId);
            if (!$targetItem || $targetItem->steam_price <= 0) {
                throw new Exception('Целевой предмет не найден');
            }

            $targetPriceRub = $targetItem->steam_price * $usdRate;

            // 4. Расчет шанса
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
