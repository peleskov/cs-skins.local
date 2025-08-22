<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInventoryItem;
use App\Models\Listing;
use App\Models\Tag;
use App\Services\Steam\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:client');
    }

    public function index()
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();
        
        if (!$client->steam_id) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо привязать Steam аккаунт для просмотра инвентаря'
            ], 400);
        }

        // Получаем кешированный инвентарь
        $inventoryItems = $client->inventoryItems()
            ->with(['item', 'tags'])
            ->orderBy('cached_at', 'desc')
            ->get();

        // Получаем список steam_asset_id, которые уже выставлены на продажу (pending, active или reserved)
        $listedAssetIds = Listing::where('seller_id', $client->id)
            ->whereIn('status', ['pending', 'active', 'reserved'])
            ->pluck('steam_asset_id')
            ->toArray();

        // Добавляем флаг is_listed к каждому предмету
        $inventoryItems->each(function ($item) use ($listedAssetIds) {
            $item->is_listed = in_array($item->steam_asset_id, $listedAssetIds);
        });

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
                'items' => $inventoryItems,
                'stats' => $stats,
                'has_trade_url' => !empty($client->steam_trade_url)
            ]
        ]);
    }

    public function sync(Request $request)
    {
        /** @var Client $client */
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


    public function createListing(Request $request)
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();
        
        $request->validate([
            'steam_asset_id' => 'required|string'
        ]);
        
        $steamAssetId = $request->steam_asset_id;
        
        // Проверяем, что у пользователя настроен Trade URL
        if (empty($client->steam_trade_url)) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо настроить Trade URL в профиле'
            ], 400);
        }
        
        // Ищем предмет в инвентаре пользователя
        $inventoryItem = ClientInventoryItem::where('client_id', $client->id)
            ->where('steam_asset_id', $steamAssetId)
            ->with('item')
            ->first();
            
        if (!$inventoryItem) {
            return response()->json([
                'success' => false,
                'message' => 'Предмет не найден в вашем инвентаре'
            ], 404);
        }
        
        // Проверяем, что предмет можно продать
        if (!$inventoryItem->tradable || !$inventoryItem->marketable) {
            return response()->json([
                'success' => false,
                'message' => 'Данный предмет нельзя продать'
            ], 400);
        }
        
        // Проверяем существующий активный листинг для этого предмета  
        $existingListing = Listing::where('steam_asset_id', $steamAssetId)
            ->where('seller_id', $client->id)
            ->whereNotIn('status', ['sold']) // исключаем проданные
            ->first();
            
        if ($existingListing) {
            if (in_array($existingListing->status, ['pending', 'active', 'reserved'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Предмет уже выставлен на продажу'
                ], 400);
            } elseif ($existingListing->status === 'cancelled') {
                // Реактивируем отмененный листинг
                try {
                    $existingListing->status = 'pending';
                    $existingListing->price = 0; // сбрасываем цену
                    $existingListing->listed_at = null;
                    $existingListing->save();
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Предмет возвращен в торговлю. Настройте цену в разделе "Торговля"',
                        'data' => [
                            'listing_id' => $existingListing->id,
                            'redirect_url' => '/profile#trading'
                        ]
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to reactivate listing', [
                        'client_id' => $client->id,
                        'listing_id' => $existingListing->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Произошла ошибка при реактивации листинга'
                    ], 500);
                }
            }
            // Убираем проверку sold статуса - таких листингов не будет в выборке
        }
        
        try {
            // Создаем листинг со стандартными параметрами
            $listing = new Listing();
            $listing->seller_id = $client->id;
            $listing->item_id = $inventoryItem->item_id; // может быть null
            $listing->steam_asset_id = $steamAssetId;
            $listing->steam_class_id = $inventoryItem->steam_class_id;
            $listing->steam_instance_id = $inventoryItem->steam_instance_id;
            $listing->steam_owner_id = $client->steam_id;
            $listing->market_hash_name = $inventoryItem->market_hash_name;
            
            // Снимок данных из инвентаря
            $listing->inventory_item_name = $inventoryItem->item_name;
            $listing->inventory_type = $inventoryItem->type;
            $listing->inventory_icon_url = $inventoryItem->icon_url;
            $listing->inventory_descriptions = $inventoryItem->descriptions;
            $listing->tradable = $inventoryItem->tradable;
            $listing->marketable = $inventoryItem->marketable;
            
            $listing->price = 0; // пользователь установит цену сам
            $listing->currency = 'RUB';
            $listing->status = 'pending';
            $listing->type = 'p2p';
            $listing->wear_condition = $inventoryItem->wear_condition;
            $listing->float_value = $inventoryItem->float_value;
            $listing->float_min = $inventoryItem->float_min;
            $listing->float_max = $inventoryItem->float_max;
            $listing->paint_index = $inventoryItem->paint_index;
            $listing->def_index = $inventoryItem->def_index;
            $listing->csfloat_id = $inventoryItem->csfloat_id;
            $listing->pattern_index = $inventoryItem->pattern_index;
            $listing->stickers = $inventoryItem->stickers;
            $listing->inspect_url = $this->generateInspectUrl($client->steam_id, $steamAssetId);
            
            // Копируем теги из новой системы
            $listing->type_id = $inventoryItem->type_id;
            $listing->quality_id = $inventoryItem->quality_id;
            $listing->rarity_id = $inventoryItem->rarity_id;
            $listing->exterior_id = $inventoryItem->exterior_id;
            
            // Обновляем флаги на основе тегов
            $listing->wear_value = $inventoryItem->float_value;
            if ($inventoryItem->quality_id) {
                $quality = Tag::find($inventoryItem->quality_id);
                if ($quality) {
                    $listing->is_stattrak = $quality->normalized_value === 'stattrak';
                    $listing->is_souvenir = $quality->normalized_value === 'souvenir';
                }
            }
            
            // Скриншоты теперь получаем через BitSkins в SkinScreenshotService
            
            $listing->save();
            
            // Копируем все теги из инвентаря в листинг используя Eloquent
            $inventoryItem->load('tags'); // Загружаем теги если еще не загружены
            
            if ($inventoryItem->tags->isNotEmpty()) {
                // Получаем ID всех тегов
                $tagIds = $inventoryItem->tags->pluck('id')->toArray();
                
                // Присоединяем теги к листингу
                // sync() автоматически добавит item_type через отношение
                $listing->tags()->sync($tagIds);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Предмет добавлен в торговлю. Настройте цену в разделе "Торговля"',
                'data' => [
                    'listing_id' => $listing->id,
                    'redirect_url' => '/profile#trading'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create listing', [
                'client_id' => $client->id,
                'steam_asset_id' => $steamAssetId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при создании листинга'
            ], 500);
        }
    }


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

    private function generateInspectUrl(string $steamId, string $assetId): string
    {
        // Конвертируем Steam ID64 в Steam ID32 для inspect URL
        $steamId32 = (string)((int)$steamId - 76561197960265728);
        
        return "steam://rungame/730/76561202255233023/+csgo_econ_action_preview S{$steamId}A{$assetId}D{$steamId32}";
    }
}
