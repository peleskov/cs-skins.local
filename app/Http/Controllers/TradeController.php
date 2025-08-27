<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:client');
    }

    /**
     * Получить все листинги пользователя
     */
    public function getMyListings()
    {
        $client = Auth::guard('client')->user();
        
        $listings = Listing::where('seller_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Для каждого листинга получаем минимальную цену (ТОП-1) и цену выкупа
        $listings->each(function ($listing) {
            $listing->min_market_price = $this->calculateMinMarketPrice($listing->market_hash_name);
            $listing->buyout_price = null; // Удалено после удаления модели Item
        });
        
        return response()->json([
            'success' => true,
            'data' => $listings
        ]);
    }

    /**
     * Обновить цену листинга
     */
    public function updateListingPrice(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        $request->validate([
            'listing_id' => 'required|integer',
            'price' => 'required|numeric|min:0.01|max:100000'
        ]);
        
        $listingId = $request->listing_id;
        $price = $request->price;
        
        // Находим листинг пользователя
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $client->id)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Листинг не найден'
            ], 404);
        }
        
        // Проверяем, что листинг можно редактировать
        if (!in_array($listing->status, ['pending', 'active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя изменить цену для этого листинга'
            ], 400);
        }
        
        // Блокируем редактирование зарезервированных листингов
        if ($listing->status === Listing::STATUS_RESERVED) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя изменить цену зарезервированного листинга'
            ], 400);
        }
        
        try {
            // Обновляем цену
            $listing->price = $price;
            $listing->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Цена обновлена',
                'data' => [
                    'listing_id' => $listing->id,
                    'new_price' => $price
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update listing price', [
                'client_id' => $client->id,
                'listing_id' => $listingId,
                'price' => $price,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении цены'
            ], 500);
        }
    }

    /**
     * Активировать листинг
     */
    public function activateListing(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        $request->validate([
            'listing_id' => 'required|integer'
        ]);
        
        $listingId = $request->listing_id;
        
        // Находим листинг пользователя
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $client->id)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Листинг не найден'
            ], 404);
        }
        
        // Проверяем, что листинг можно активировать
        if (!in_array($listing->status, ['pending', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Листинг уже активирован или не может быть активирован'
            ], 400);
        }
        
        // Блокируем активацию зарезервированных листингов
        if ($listing->status === Listing::STATUS_RESERVED) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя активировать зарезервированный листинг'
            ], 400);
        }
        
        // Проверяем, что у пользователя настроен Trade URL
        if (empty($client->steam_trade_url)) {
            return response()->json([
                'success' => false,
                'message' => 'Для активации листинга необходимо настроить Trade URL в профиле',
                'require_trade_url' => true
            ], 400);
        }
        
        // Проверяем, что цена установлена
        if ($listing->price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Сначала установите цену для листинга'
            ], 400);
        }
        
        try {
            // Активируем листинг
            $listing->status = 'active';
            $listing->listed_at = now();
            $listing->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Листинг активирован',
                'data' => [
                    'listing_id' => $listing->id,
                    'status' => $listing->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to activate listing', [
                'client_id' => $client->id,
                'listing_id' => $listingId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при активации листинга'
            ], 500);
        }
    }

    /**
     * Деактивировать листинг
     */
    public function deactivateListing(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        $request->validate([
            'listing_id' => 'required|integer'
        ]);
        
        $listingId = $request->listing_id;
        
        // Находим листинг пользователя
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $client->id)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Листинг не найден'
            ], 404);
        }
        
        // Проверяем, что листинг в статусе active
        if ($listing->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Листинг не активен или уже деактивирован'
            ], 400);
        }
        
        // Блокируем деактивацию зарезервированных листингов
        if ($listing->status === Listing::STATUS_RESERVED) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя деактивировать зарезервированный листинг'
            ], 400);
        }
        
        try {
            // Деактивируем листинг
            $listing->status = 'pending';
            $listing->listed_at = null;
            $listing->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Листинг деактивирован',
                'data' => [
                    'listing_id' => $listing->id,
                    'status' => $listing->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to deactivate listing', [
                'client_id' => $client->id,
                'listing_id' => $listingId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при деактивации листинга'
            ], 500);
        }
    }

    /**
     * Удалить листинг
     */
    public function deleteListing(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        $request->validate([
            'listing_id' => 'required|integer'
        ]);
        
        $listingId = $request->listing_id;
        
        // Находим листинг пользователя
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $client->id)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Листинг не найден'
            ], 404);
        }
        
        // Проверяем, что листинг можно отменить
        if ($listing->status === 'sold') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить проданный листинг'
            ], 400);
        }
        
        if ($listing->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Листинг уже отменен'
            ], 400);
        }
        
        // Блокируем удаление зарезервированных листингов
        if ($listing->status === Listing::STATUS_RESERVED) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить зарезервированный листинг'
            ], 400);
        }
        
        try {
            // Отменяем листинг вместо удаления
            $listing->cancel();
            
            return response()->json([
                'success' => true,
                'message' => 'Предмет удален из торговли'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete listing', [
                'client_id' => $client->id,
                'listing_id' => $listingId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при удалении листинга'
            ], 500);
        }
    }

    /**
     * Получить минимальную цену для предмета в маркетплейсе (ТОП-1)
     */
    public function getMinMarketPrice(Request $request)
    {
        $request->validate([
            'market_hash_name' => 'required|string'
        ]);
        
        $marketHashName = $request->market_hash_name;
        $minPrice = $this->calculateMinMarketPrice($marketHashName);
        
        return response()->json([
            'success' => true,
            'data' => [
                'market_hash_name' => $marketHashName,
                'min_market_price' => $minPrice
            ]
        ]);
    }

    /**
     * Получить минимальную цену для предмета в маркетплейсе (ТОП-1)
     */
    private function calculateMinMarketPrice(string $marketHashName): ?float
    {
        $minPrice = Listing::where('market_hash_name', $marketHashName)
            ->where('status', 'active')
            ->min('price');
            
        return $minPrice ? (float) $minPrice : null;
    }


    /**
     * Конвертировать цену из долларов в рубли
     */
    private function convertDollarsToRubles(float $priceInDollars): float
    {
        return round(\App\Models\Currency::convert($priceInDollars, 'USD', 'RUB'), 2);
    }
}