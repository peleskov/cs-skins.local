<?php

namespace App\Services;

use App\Models\CaseInventoryItem;
use App\Models\CaseInventoryReplacement;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;

class WithdrawService
{
    /**
     * Диапазоны цен для замены (из ТЗ)
     */
    private const PRICE_RANGES = [
        100 => 0.10,
        1000 => 0.07,
        10000 => 0.05,
        50000 => 0.04,
        100000 => 0.02,
        PHP_INT_MAX => 0.02,
    ];

    /**
     * Найти точный предмет на маркетплейсе
     */
    public function findExactListing(CaseInventoryItem $item): ?Listing
    {
        $virtualItem = $item->virtualItem;

        if (!$virtualItem) {
            return null;
        }

        $shortQuality = $virtualItem->getShortQuality();
        $onlineSellerIds = $this->getOnlineSellerIds();

        $query = Listing::where('market_hash_name', $virtualItem->market_hash_name)
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereIn('seller_id', $onlineSellerIds)
            ->where('seller_id', '!=', $item->client_id);

        if ($shortQuality) {
            $query->where(function ($q) use ($shortQuality) {
                $q->where('wear_condition', $shortQuality)
                  ->orWhereRaw("LOWER(wear_condition) = ?", [strtolower($shortQuality)]);
            });
        }

        return $query->orderBy('price', 'asc')->first();
    }

    /**
     * Получить диапазон цен для замены
     */
    public function getPriceRange(float $price): array
    {
        $percent = 0.02;

        foreach (self::PRICE_RANGES as $threshold => $range) {
            if ($price < $threshold) {
                $percent = $range;
                break;
            }
        }

        return [
            'min' => round($price * (1 - $percent), 2),
            'max' => round($price * (1 + $percent), 2),
            'percent' => $percent * 100,
        ];
    }

    /**
     * Найти замены для предмета
     *
     * Ищем только в ценовом диапазоне согласно PRICE_RANGES
     */
    public function findReplacements(CaseInventoryItem $item, ?string $search = null): Collection
    {
        $range = $this->getPriceRange((float) $item->price);
        $onlineSellerIds = $this->getOnlineSellerIds();

        $query = Listing::where('status', Listing::STATUS_ACTIVE)
            ->whereIn('seller_id', $onlineSellerIds)
            ->where('seller_id', '!=', $item->client_id)
            ->whereBetween('price', [$range['min'], $range['max']]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('market_hash_name', 'like', "%{$search}%")
                  ->orWhere('inventory_item_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('price', 'desc')->limit(50)->get();
    }

    /**
     * Получить ID онлайн продавцов из Redis
     */
    private function getOnlineSellerIds(): array
    {
        // Очищаем устаревшие записи
        Redis::zremrangebyscore('online_sellers', '-inf', now()->timestamp);

        // Получаем актуальных онлайн продавцов
        $ids = Redis::zrangebyscore('online_sellers', now()->timestamp, '+inf');

        // Если нет онлайн — возвращаем [0] чтобы whereIn вернул пустой результат
        return !empty($ids) ? $ids : [0];
    }

    /**
     * Выполнить вывод предмета
     */
    public function withdraw(CaseInventoryItem $item, Client $client, ?int $replacementListingId = null): array
    {
        if ($client->isWithdrawBlocked()) {
            throw new Exception($client->getWithdrawBlockReasonForUser() ?: 'Вывод заблокирован администратором');
        }

        if (!$item->isAvailable()) {
            throw new Exception('Предмет недоступен для вывода');
        }

        if ($item->client_id !== $client->id) {
            throw new Exception('Предмет не принадлежит пользователю');
        }

        if (empty($client->steam_trade_url)) {
            throw new Exception('Необходимо указать Trade URL');
        }

        $listing = $replacementListingId
            ? Listing::where('id', $replacementListingId)->where('status', Listing::STATUS_ACTIVE)->first()
            : $this->findExactListing($item);

        if (!$listing) {
            // Не бросаем исключение, возвращаем флаг для показа замен
            return [
                'success' => false,
                'need_replacement' => true,
            ];
        }

        if (!$listing->isActive()) {
            throw new Exception('Выбранный предмет уже недоступен');
        }

        return DB::transaction(function () use ($item, $client, $listing, $replacementListingId) {
            $order = $this->createWithdrawOrder($client, $listing, $item);

            if ($replacementListingId) {
                CaseInventoryReplacement::create([
                    'case_inventory_item_id' => $item->id,
                    'listing_id' => $listing->id,
                    'original_price' => $item->price,
                    'replacement_price' => $listing->price,
                    'status' => CaseInventoryReplacement::STATUS_PENDING,
                ]);
            }

            $item->update(['status' => CaseInventoryItem::STATUS_PENDING_WITHDRAWAL]);

            return [
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
                'listing' => $this->formatListing($listing),
            ];
        });
    }

    /**
     * Создать заказ для вывода (без списания с покупателя)
     */
    private function createWithdrawOrder(Client $buyer, Listing $listing, CaseInventoryItem $inventoryItem): Order
    {
        $items = [
            [
                'listing_id' => $listing->id,
                'item' => [
                    'name' => $listing->inventory_item_name,
                    'image_url' => $listing->inventory_icon_url
                        ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url
                        : null,
                    'market_hash_name' => $listing->market_hash_name,
                    'steam_asset_id' => $listing->steam_asset_id,
                ],
                'price' => (float) $listing->price,
                'wear_name' => $listing->wear_name,
                'wear_value' => (float) ($listing->wear_value ?? 0),
                'is_stattrak' => $listing->is_stattrak ?? false,
                'is_souvenir' => $listing->is_souvenir ?? false,
                'seller_id' => $listing->seller_id,
                'seller' => [
                    'id' => $listing->seller_id,
                    'name' => $listing->seller->name ?? 'Продавец',
                ],
                'case_inventory_item_id' => $inventoryItem->id,
            ]
        ];

        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $listing->seller_id,
            'total_amount' => (float) $listing->price,
            'cart_snapshot' => $items,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
            'payment_transaction_id' => 'CASE_WITHDRAW_' . $inventoryItem->id . '_' . uniqid(),
            'payment_method' => 'case_withdraw',
            'notes' => 'Вывод предмета из инвентаря кейсов',
        ]);
    }

    /**
     * Форматировать листинг для API
     */
    private function formatListing(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'name' => $listing->inventory_item_name,
            'market_hash_name' => $listing->market_hash_name,
            'price' => (float) $listing->price,
            'image_url' => $listing->inventory_icon_url
                ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url
                : null,
            'wear_name' => $listing->wear_name,
            'float_value' => $listing->float_value,
            'is_stattrak' => $listing->is_stattrak,
            'is_souvenir' => $listing->is_souvenir,
        ];
    }
}
