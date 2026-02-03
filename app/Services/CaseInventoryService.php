<?php

namespace App\Services;

use App\Models\CaseInventoryItem;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\VirtualItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CaseInventoryService
{
    /**
     * Добавить предмет в инвентарь пользователя
     */
    public function addItem(
        Client $client,
        VirtualItem $virtualItem,
        float $price,
        string $sourceType,
        int $sourceId
    ): CaseInventoryItem {
        return CaseInventoryItem::create([
            'client_id' => $client->id,
            'virtual_item_id' => $virtualItem->id,
            'price' => $price,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => CaseInventoryItem::STATUS_AVAILABLE,
        ]);
    }

    /**
     * Получить предметы пользователя
     */
    public function getItems(Client $client, ?string $status = null): Collection
    {
        $query = CaseInventoryItem::where('client_id', $client->id)
            ->with('virtualItem')
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Продать предметы по ID
     *
     * @param Client $client
     * @param array $itemIds Массив ID предметов
     * @return array ['sold_count' => int, 'total_amount' => float, 'sold_ids' => array]
     */
    public function sellItems(Client $client, array $itemIds): array
    {
        if (empty($itemIds)) {
            throw new \Exception('Не указаны предметы для продажи');
        }

        return DB::transaction(function () use ($client, $itemIds) {
            // Получаем предметы клиента со статусом available
            $items = CaseInventoryItem::where('client_id', $client->id)
                ->whereIn('id', $itemIds)
                ->where('status', CaseInventoryItem::STATUS_AVAILABLE)
                ->with('virtualItem')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw new \Exception('Предметы не найдены или недоступны для продажи');
            }

            $totalAmount = 0;
            $soldIds = [];
            $itemNames = [];

            foreach ($items as $item) {
                $totalAmount += (float) $item->price;
                $soldIds[] = $item->id;
                $itemNames[] = $item->virtualItem->name;
                $item->update(['status' => CaseInventoryItem::STATUS_SOLD]);
            }

            // Зачисляем на основной баланс
            $client->increment('balance', $totalAmount);

            // Создаём транзакцию
            $count = count($soldIds);
            $description = $count === 1
                ? 'Продажа предмета: ' . $itemNames[0]
                : "Продажа {$count} предметов из инвентаря кейсов";

            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_VIRTUAL_ITEM_SALE,
                'amount' => $totalAmount,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $description,
                'metadata' => [
                    'sold_ids' => $soldIds,
                    'items_count' => $count,
                ],
            ]);

            return [
                'sold_count' => $count,
                'total_amount' => $totalAmount,
                'sold_ids' => $soldIds,
            ];
        });
    }

    /**
     * Продать все доступные предметы
     */
    public function sellAllItems(Client $client): array
    {
        $itemIds = CaseInventoryItem::where('client_id', $client->id)
            ->where('status', CaseInventoryItem::STATUS_AVAILABLE)
            ->pluck('id')
            ->toArray();

        if (empty($itemIds)) {
            return [
                'sold_count' => 0,
                'total_amount' => 0,
                'sold_ids' => [],
            ];
        }

        return $this->sellItems($client, $itemIds);
    }

    /**
     * Отметить предмет как использованный в апгрейде
     */
    public function markAsUpgraded(CaseInventoryItem $item): void
    {
        if (!$item->isAvailable()) {
            throw new \Exception('Предмет недоступен');
        }

        $item->update(['status' => CaseInventoryItem::STATUS_UPGRADED]);
    }

    /**
     * Отметить предмет как выведенный
     */
    public function markAsWithdrawn(CaseInventoryItem $item): void
    {
        if (!$item->isAvailable()) {
            throw new \Exception('Предмет недоступен');
        }

        $item->update(['status' => CaseInventoryItem::STATUS_WITHDRAWN]);
    }
}
