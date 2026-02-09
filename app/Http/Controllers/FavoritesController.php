<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FavoritesController extends Controller
{
    /**
     * Показать страницу избранного в профиле
     */
    public function index(): View
    {
        $favorites = Favorite::with(['listing.seller'])
            ->where('client_id', auth('client')->id())
            ->whereHas('listing', function($query) {
                $query->where('status', 'active');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Добавляем статус корзины для каждого товара (читаем из сессии)
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        
        $favorites->each(function ($favorite) use ($cartItemIds) {
            if ($favorite->listing) {
                $favorite->listing->is_in_cart = $cartItemIds->contains($favorite->listing->id);
                // Все товары в избранном по умолчанию имеют is_favorite = true
                $favorite->listing->is_favorite = true;
                // Добавляем структурированные теги
                $favorite->listing->structured_tags = $favorite->listing->structured_tags;
            }
        });

        return view('profile.favorites', compact('favorites'));
    }

    /**
     * API: Toggle товар в избранном (добавить/удалить)
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'listing_id' => 'required|integer|exists:listings,id',
        ]);

        $clientId = auth('client')->id();
        $listingId = $request->listing_id;

        // Проверяем, есть ли товар в избранном
        $favorite = Favorite::where('client_id', $clientId)
            ->where('listing_id', $listingId)
            ->first();

        if ($favorite) {
            // Удаляем из избранного
            $favorite->delete();
            $isFavorite = false;
            $message = 'Товар удален из избранного';
        } else {
            // Добавляем в избранное
            Favorite::create([
                'client_id' => $clientId,
                'listing_id' => $listingId,
            ]);
            $isFavorite = true;
            $message = 'Товар добавлен в избранное';
        }

        // Получаем общее количество избранного для счетчика (только активные товары)
        $favoritesCount = Favorite::where('client_id', $clientId)
            ->whereHas('listing', function($query) {
                $query->where('status', 'active');
            })
            ->count();

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
            'message' => $message,
            'favorites_count' => $favoritesCount,
        ]);
    }

    /**
     * API: Получить список избранного
     */
    public function getFavorites(): JsonResponse
    {
        $favorites = Favorite::with(['listing.seller'])
            ->where('client_id', auth('client')->id())
            ->whereHas('listing', function($query) {
                $query->where('status', 'active');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Добавляем статус корзины для каждого товара (читаем из сессии)
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        
        // Получаем заблокированные аукционами листинги
        $listingIds = $favorites->pluck('listing.id')->filter();
        $blockedIds = Auction::with('listing')
            ->where('status', Auction::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->filter(fn ($auction) => $auction->isPurchaseBlocked())
            ->pluck('listing_id');

        $favorites->each(function ($favorite) use ($cartItemIds, $blockedIds) {
            if ($favorite->listing) {
                $favorite->listing->is_in_cart = $cartItemIds->contains($favorite->listing->id);
                $favorite->listing->is_favorite = true;
                $favorite->listing->structured_tags = $favorite->listing->structured_tags;
                $favorite->listing->purchase_blocked = $blockedIds->contains($favorite->listing->id);
            }
        });

        return response()->json([
            'success' => true,
            'favorites' => $favorites,
        ]);
    }

    /**
     * API: Проверить статус товара в избранном
     */
    public function check(int $listingId): JsonResponse
    {
        $isFavorite = Favorite::where('client_id', auth('client')->id())
            ->where('listing_id', $listingId)
            ->exists();

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
        ]);
    }
}