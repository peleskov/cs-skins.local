<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInventoryItem;
use App\Services\SteamInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:client');
    }

    public function index()
    {
        $client = Auth::guard('client')->user();
        
        if (!$client->steam_id) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо привязать Steam аккаунт для просмотра инвентаря'
            ], 400);
        }

        // Получаем кешированный инвентарь
        $inventoryItems = $client->inventoryItems()
            ->with('item')
            ->orderBy('cached_at', 'desc')
            ->get();

        // Убрали автоматическую синхронизацию - только по кнопке

        // Статистика инвентаря
        $stats = [
            'total_items' => $inventoryItems->count(),
            'tradable_items' => $inventoryItems->where('tradable', true)->count(),
            'marketable_items' => $inventoryItems->where('marketable', true)->count(),
            'estimated_value' => $inventoryItems->sum(function ($item) {
                return $item->item ? $item->item->min_steam_price ?? 0 : 0;
            }),
            'last_sync' => $inventoryItems->first()?->cached_at,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $inventoryItems->toArray(),
                'stats' => $stats
            ]
        ]);
    }

    public function sync(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        if (!$client->steam_id) {
            return response()->json([
                'success' => false,
                'message' => 'Steam аккаунт не привязан'
            ]);
        }

        // Проверяем, прошло ли 2 минуты с последней синхронизации
        $lastSync = $client->inventoryItems()
            ->orderBy('cached_at', 'desc')
            ->first();
            
        if ($lastSync && $lastSync->cached_at->addMinutes(2)->isFuture()) {
            $remainingTime = $lastSync->cached_at->addMinutes(2)->diffInSeconds(now());
            $remainingMinutes = ceil($remainingTime / 60);
            
            return response()->json([
                'success' => false,
                'message' => "Следующее обновление инвентаря будет доступно через {$remainingMinutes} мин",
                'data' => [
                    'cooldown_remaining' => $remainingTime,
                    'next_sync_at' => $lastSync->cached_at->addMinutes(2)->toISOString()
                ]
            ]);
        }

        try {
            $result = $this->syncInventory($client);
            
            return response()->json([
                'success' => true,
                'message' => 'Инвентарь обновлен',
                'data' => [
                    'items_count' => $result['items_count'],
                    'sync_time' => $result['sync_time']
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Inventory sync failed', [
                'client_id' => $client->id,
                'steam_id' => $client->steam_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка синхронизации инвентаря: ' . $e->getMessage()
            ]);
        }
    }

    /*
    public function show($assetId)
    {
        $client = Auth::guard('client')->user();
        
        $inventoryItem = ClientInventoryItem::where('client_id', $client->id)
            ->where('steam_asset_id', $assetId)
            ->with('item')
            ->firstOrFail();

        return view('inventory.show', compact('inventoryItem'));
    }

    public function sell($assetId)
    {
        $client = Auth::guard('client')->user();
        
        $inventoryItem = ClientInventoryItem::where('client_id', $client->id)
            ->where('steam_asset_id', $assetId)
            ->with('item')
            ->firstOrFail();

        // Проверяем что предмет можно продать
        if (!$inventoryItem->tradable) {
            return redirect()->back()
                ->with('error', 'Данный предмет нельзя продать');
        }

        // Проверяем что предмет все еще в инвентаре Steam
        if (!$this->steamInventoryService->validateItemOwnership($client->steam_id, $assetId)) {
            return redirect()->route('inventory.index')
                ->with('error', 'Предмет больше не найден в вашем Steam инвентаре');
        }

        // Получаем рекомендуемую цену
        $recommendedPrice = $this->calculateRecommendedPrice($inventoryItem);

        return view('inventory.sell', compact('inventoryItem', 'recommendedPrice'));
    }

    public function createListing(Request $request, $assetId)
    {
        return redirect()->route('inventory.index')
            ->with('success', 'Функция продажи будет реализована позже');
    }
    */

    private function syncInventory(Client $client): array
    {
        $startTime = microtime(true);
        
        // Запускаем команду синхронизации
        Artisan::call('inventory:sync', [
            'steam_id' => $client->steam_id,
            '--force' => true
        ]);

        $endTime = microtime(true);
        $syncTime = round(($endTime - $startTime) * 1000); // в миллисекундах

        $itemsCount = $client->inventoryItems()->count();

        return [
            'items_count' => $itemsCount,
            'sync_time' => $syncTime
        ];
    }

    /*
    private function calculateRecommendedPrice(ClientInventoryItem $inventoryItem): float
    {
        if (!$inventoryItem->item || !$inventoryItem->item->min_steam_price) {
            return 0;
        }

        $basePrice = $inventoryItem->item->min_steam_price;
        
        // Корректируем цену в зависимости от float (износа)
        if ($inventoryItem->float_value) {
            $wearMultiplier = $this->getWearMultiplier($inventoryItem->float_value);
            $basePrice *= $wearMultiplier;
        }

        // Добавляем premium за стикеры (упрощенно)
        if ($inventoryItem->stickers && count($inventoryItem->stickers) > 0) {
            $basePrice *= 1.1; // +10% за стикеры
        }

        return round($basePrice, 2);
    }

    private function getWearMultiplier(float $floatValue): float
    {
        // Упрощенная логика корректировки цены по износу
        if ($floatValue <= 0.07) {
            return 1.2; // Factory New - +20%
        } elseif ($floatValue <= 0.15) {
            return 1.1; // Minimal Wear - +10%
        } elseif ($floatValue <= 0.38) {
            return 1.0; // Field-Tested - без изменений
        } elseif ($floatValue <= 0.45) {
            return 0.9; // Well-Worn - -10%
        } else {
            return 0.8; // Battle-Scarred - -20%
        }
    }
    */
}
