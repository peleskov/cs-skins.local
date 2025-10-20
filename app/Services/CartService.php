<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Collection;

class CartService
{
    private const CART_SESSION_KEY = 'shopping_cart';

    /**
     * Добавить товар в корзину
     */
    public function add(int $listingId): array
    {
        $listing = Listing::find($listingId);
        
        if (!$listing) {
            throw new \Exception('Товар не найден');
        }

        if (!$listing->isActive()) {
            throw new \Exception('Товар недоступен для покупки');
        }

        // Проверяем, что пользователь не пытается добавить свой собственный товар
        if (auth('client')->check() && $listing->seller_id === auth('client')->id()) {
            throw new \Exception('Нельзя добавить собственный товар в корзину');
        }

        $cart = $this->getCart();
        
        // Проверяем, нет ли уже этого товара в корзине
        if ($cart->has($listingId)) {
            throw new \Exception('Товар уже в корзине');
        }

        $cartItem = [
            'listing_id' => $listing->id,
            'item_name' => $listing->inventory_item_name,
            'item_image' => $listing->inventory_icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url : null,
            'price' => (float) $listing->price,
            'wear_name' => $listing->wear_name,
            'is_stattrak' => $listing->is_stattrak,
            'is_souvenir' => $listing->is_souvenir,
            'added_at' => now()->toISOString(),
        ];

        $cart->put($listingId, $cartItem);
        $this->saveCart($cart);

        return $cartItem;
    }

    /**
     * Удалить товар из корзины
     */
    public function remove(int $listingId): bool
    {
        $cart = $this->getCart();
        
        if (!$cart->has($listingId)) {
            return false;
        }

        $cart->forget($listingId);
        $this->saveCart($cart);

        return true;
    }

    /**
     * Получить содержимое корзины
     */
    public function getItems(): Collection
    {
        return $this->getCart();
    }

    /**
     * Получить количество товаров в корзине
     */
    public function getCount(): int
    {
        return $this->getCart()->count();
    }

    /**
     * Получить общую стоимость корзины
     */
    public function getTotal(): float
    {
        return $this->getDetailedItems()->sum('price');
    }

    /**
     * Очистить корзину
     */
    public function clear(): void
    {
        session()->forget(self::CART_SESSION_KEY);
    }

    /**
     * Проверить, есть ли товар в корзине
     */
    public function has(int $listingId): bool
    {
        return $this->getCart()->has($listingId);
    }

    /**
     * Валидация корзины (удаление недоступных товаров)
     */
    public function validate(): array
    {
        $cart = $this->getCart();
        $removedItems = [];

        if ($cart->isEmpty()) {
            return $removedItems;
        }

        $listingIds = $cart->keys();
        $activeListings = Listing::whereIn('id', $listingIds)
            ->where('status', Listing::STATUS_ACTIVE)
            ->pluck('id')
            ->toArray();

        foreach ($cart as $listingId => $item) {
            if (!in_array($listingId, $activeListings)) {
                $removedItems[] = $item;
                $cart->forget($listingId);
            }
        }

        if (!empty($removedItems)) {
            $this->saveCart($cart);
        }

        return $removedItems;
    }

    /**
     * Получить детальную информацию о товарах в корзине
     */
    public function getDetailedItems(): Collection
    {
        $cart = $this->getCart();
        
        if ($cart->isEmpty()) {
            return collect();
        }

        $listingIds = $cart->keys();
        $listings = Listing::with('seller')
            ->whereIn('id', $listingIds)
            ->where('status', Listing::STATUS_ACTIVE)
            ->get()
            ->keyBy('id');

        return $cart->map(function ($cartItem, $listingId) use ($listings) {
            if (!$listings->has($listingId)) {
                return null; // Товар не найден или неактивен
            }

            $listing = $listings->get($listingId);
            
            return [
                'listing_id' => $listing->id,
                'item' => [
                    'name' => $listing->inventory_item_name,
                    'image_url' => $listing->inventory_icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url : null,
                    'type' => $listing->inventory_type,
                    'market_hash_name' => $listing->market_hash_name,
                    'steam_asset_id' => $listing->steam_asset_id,
                ],
                'price' => (float) $listing->price,
                'wear_name' => $listing->wear_name,
                'wear_value' => (float) $listing->wear_value,
                'is_stattrak' => $listing->is_stattrak,
                'is_souvenir' => $listing->is_souvenir,
                'seller_id' => $listing->seller->id,
                'seller' => [
                    'id' => $listing->seller->id,
                    'name' => $listing->seller->name,
                ],
                'added_at' => $cartItem['added_at'],
            ];
        })->filter(); // Убираем null значения
    }

    /**
     * Получить корзину из сессии
     */
    private function getCart(): Collection
    {
        return collect(session()->get(self::CART_SESSION_KEY, []));
    }

    /**
     * Сохранить корзину в сессию
     */
    private function saveCart(Collection $cart): void
    {
        session()->put(self::CART_SESSION_KEY, $cart->toArray());
    }
}