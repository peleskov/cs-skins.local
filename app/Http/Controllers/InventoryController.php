<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInventoryItem;
use App\Models\Listing;
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
        
        // Проверяем существующий листинг для этого предмета
        $existingListing = Listing::where('steam_asset_id', $steamAssetId)
            ->where('seller_id', $client->id)
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
            } elseif ($existingListing->status === 'sold') {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот предмет уже продан'
                ], 400);
            }
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
                $quality = \DB::table('tags')->where('id', $inventoryItem->quality_id)->first();
                if ($quality) {
                    $listing->is_stattrak = $quality->normalized_value === 'stattrak';
                    $listing->is_souvenir = $quality->normalized_value === 'souvenir';
                }
            }
            
            // Получаем скриншот через Swap.gg API и сохраняем на диск
            Log::info('Attempting to get screenshot', [
                'inspect_url' => $listing->inspect_url,
                'steam_asset_id' => $steamAssetId
            ]);
            
            $floatData = $this->getScreenshotFromSwapGG($listing->inspect_url, $steamAssetId);
            
            Log::info('Screenshot result', [
                'float_data' => $floatData,
                'steam_asset_id' => $steamAssetId
            ]);
            
            // Обновляем float если получили более точное значение
            if ($floatData && isset($floatData['float'])) {
                $listing->float_value = (float)$floatData['float'];
                Log::info('Updated float value', [
                    'new_float' => $listing->float_value,
                    'steam_asset_id' => $steamAssetId
                ]);
            }
            
            $listing->save();
            
            // Копируем все теги из инвентаря в листинг
            $inventoryTags = \DB::table('item_tags')
                ->where('item_id', $inventoryItem->id)
                ->where('item_type', 'inventory')
                ->get();
                
            if ($inventoryTags->count() > 0) {
                $listingTags = [];
                foreach ($inventoryTags as $tag) {
                    $listingTags[] = [
                        'item_id' => $listing->id,
                        'item_type' => 'listing',
                        'tag_id' => $tag->tag_id,
                    ];
                }
                \DB::table('item_tags')->insert($listingTags);
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

    private function generateInspectUrl(string $steamId, string $assetId): string
    {
        // Конвертируем Steam ID64 в Steam ID32 для inspect URL
        $steamId32 = (string)((int)$steamId - 76561197960265728);
        
        return "steam://rungame/730/76561202255233023/+csgo_econ_action_preview S{$steamId}A{$assetId}D{$steamId32}";
    }

    private function getScreenshotFromSwapGG(string $inspectUrl, string $steamAssetId): ?array
    {
        try {
            // Проверяем, существует ли уже скриншот
            $screenshotsDir = storage_path('app/public/screenshots');
            $filename = $steamAssetId . '.jpg';
            $fullPath = $screenshotsDir . '/' . $filename;
            
            Log::info('Checking screenshot file', [
                'file_path' => $fullPath,
                'exists' => file_exists($fullPath)
            ]);
            
            if (file_exists($fullPath)) {
                // Файл уже существует, не скачиваем повторно
                Log::info('Screenshot file already exists, skipping download');
                return null;
            }
            
            Log::info('Making API request to Swap.gg');
            
            // Сначала получаем сессию
            $sessionResponse = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ])
                ->get('https://swap.gg/cs2-inspects');
                
            // Извлекаем куки из ответа
            $cookies = [];
            foreach ($sessionResponse->headers()['Set-Cookie'] ?? [] as $cookie) {
                if (strpos($cookie, 'SwapSession=') === 0) {
                    $cookies['SwapSession'] = explode(';', explode('=', $cookie, 2)[1])[0];
                    break;
                }
            }
            
            if (empty($cookies)) {
                Log::error('Failed to get SwapSession cookie');
                return null;
            }
            
            $apiUrl = 'https://api.swap.gg/v2/screenshot?' . http_build_query([
                'inspectLink' => $inspectUrl
            ]);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://swap.gg/',
                    'Cookie' => 'SwapSession=' . $cookies['SwapSession']
                ])
                ->get($apiUrl);
            
            Log::info('Swap.gg API response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Parsed JSON response', ['data' => $data]);
                
                if (isset($data['status']) && $data['status'] === 'OK' && isset($data['result']['imageId'])) {
                    $imageId = $data['result']['imageId'];
                    $screenshotUrl = "https://s.swap.gg/{$imageId}.jpg";
                    
                    // Ждем пока изображение будет готово (максимум 5 попыток)
                    $maxAttempts = 5;
                    $attempt = 0;
                    
                    while ($attempt < $maxAttempts) {
                        $imageResponse = Http::timeout(10)->get($screenshotUrl);
                        
                        if ($imageResponse->successful()) {
                            if (!file_exists($screenshotsDir)) {
                                mkdir($screenshotsDir, 0755, true);
                            }
                            
                            file_put_contents($fullPath, $imageResponse->body());
                            break;
                        }
                        
                        $attempt++;
                        sleep(30); // Ждем 30 секунд перед следующей попыткой
                    }
                    
                    // Возвращаем данные для обновления float
                    $floatData = ['float' => $data['result']['meta']['16']['o'] ?? null];
                    return $floatData;
                } else {
                    Log::error('Swap.gg API returned error', [
                        'inspect_url' => $inspectUrl,
                        'steam_asset_id' => $steamAssetId,
                        'response' => $data
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error requesting screenshot from Swap.gg', [
                'inspect_url' => $inspectUrl,
                'steam_asset_id' => $steamAssetId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
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
