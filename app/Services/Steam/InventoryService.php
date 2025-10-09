<?php

namespace App\Services\Steam;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    private const STEAM_INVENTORY_URL = 'https://steamcommunity.com/inventory/%s/730/2';
    private const CS2_APP_ID = 730;
    private const CACHE_DURATION = 600; // 10 минут
    
    private string $currentSteamId = '';

    public function getInventory(string $steamId): array
    {
        $this->currentSteamId = $steamId;
        $cacheKey = "steam_inventory_{$steamId}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($steamId) {
            return $this->fetchInventoryFromSteam($steamId);
        });
    }

    public function refreshInventory(string $steamId): array
    {
        $cacheKey = "steam_inventory_{$steamId}";
        Cache::forget($cacheKey);
        
        return $this->getInventory($steamId);
    }

    private function fetchInventoryFromSteam(string $steamId): array
    {
        try {
            $allAssets = [];
            $allDescriptions = [];
            $startAssetId = null;
            $pageCount = 0;
            $maxPages = 50; // Защита от бесконечного цикла (50 страниц * 1000 предметов = ~50000 макс)

            // Загружаем все страницы инвентаря (как в steamcommunity getUserInventoryContents)
            do {
                $url = sprintf(self::STEAM_INVENTORY_URL, $steamId);

                // Параметры запроса согласно Steam API
                $queryParams = [
                    'l' => 'english',  // Язык
                    'count' => 1000,   // Максимум предметов за раз
                ];

                // Добавляем start_assetid для пагинации (начиная со второй страницы)
                if ($startAssetId !== null) {
                    $queryParams['start_assetid'] = $startAssetId;
                }

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Referer' => "https://steamcommunity.com/profiles/{$steamId}/inventory"
                    ])
                    ->get($url, $queryParams);

                if (!$response->successful()) {
                    Log::warning("Failed to fetch Steam inventory for {$steamId} (page " . ($pageCount + 1) . ")", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);

                    // Если первая страница не загрузилась - возвращаем пустой массив
                    if ($pageCount === 0) {
                        return [];
                    }

                    // Если это не первая страница - прерываем и возвращаем что уже загрузили
                    break;
                }

                $data = $response->json();

                // Проверка на пустой инвентарь
                if (isset($data['success']) && $data['success'] && isset($data['total_inventory_count']) && $data['total_inventory_count'] === 0) {
                    Log::info("Empty inventory for {$steamId}");
                    return [];
                }

                // Специальный случай для CS2 (appID 730) - инвентарь без видимых предметов
                if (isset($data['success']) && $data['success'] && !isset($data['assets'])) {
                    Log::info("CS2 inventory has no visible items for {$steamId}");
                    return [];
                }

                if (!isset($data['assets'], $data['descriptions']) || !$data['success']) {
                    // Если это первая страница и данных нет - ошибка
                    if ($pageCount === 0) {
                        Log::info("Invalid inventory response for {$steamId}", [
                            'response' => $data
                        ]);
                        return [];
                    }

                    // Прерываем загрузку
                    break;
                }

                // Добавляем данные из текущей страницы
                $allAssets = array_merge($allAssets, $data['assets']);

                // Descriptions могут повторяться, но это не проблема
                $allDescriptions = array_merge($allDescriptions, $data['descriptions']);

                $pageCount++;

                // Проверяем есть ли еще предметы (как в steamcommunity)
                $hasMoreItems = isset($data['more_items']) && $data['more_items'];
                $startAssetId = $hasMoreItems ? ($data['last_assetid'] ?? null) : null;

                Log::info("Loaded Steam inventory page {$pageCount} for {$steamId}", [
                    'assets_on_page' => count($data['assets']),
                    'total_assets_loaded' => count($allAssets),
                    'has_more_items' => $hasMoreItems,
                    'last_assetid' => $startAssetId
                ]);

                // Небольшая задержка между запросами, чтобы не нарваться на rate limit
                if ($hasMoreItems && $pageCount < $maxPages) {
                    usleep(500000); // 0.5 секунды
                }

            } while ($hasMoreItems && $startAssetId && $pageCount < $maxPages);

            if ($pageCount >= $maxPages) {
                Log::warning("Reached max pages limit for {$steamId}", [
                    'max_pages' => $maxPages,
                    'total_assets' => count($allAssets)
                ]);
            }

            // Парсим объединенные данные
            $combinedData = [
                'assets' => $allAssets,
                'descriptions' => $allDescriptions
            ];

            Log::info("Finished loading Steam inventory for {$steamId}", [
                'total_pages' => $pageCount,
                'total_assets' => count($allAssets),
                'total_descriptions' => count($allDescriptions)
            ]);

            return $this->parseInventoryData($combinedData);

        } catch (\Exception $e) {
            Log::error("Error fetching Steam inventory for {$steamId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function parseInventoryData(array $data): array
    {
        $assets = $data['assets'] ?? [];
        $descriptions = $data['descriptions'] ?? [];

        // Создаем индекс описаний для быстрого поиска (как в steamcommunity)
        $descriptionMap = [];
        foreach ($descriptions as $description) {
            // instanceID может быть пустым, в таком случае используем '0' (как в steamcommunity)
            $instanceId = $description['instanceid'] ?? '0';
            $key = $description['classid'] . '_' . $instanceId;
            $descriptionMap[$key] = $description;
        }

        $items = [];

        foreach ($assets as $asset) {
            // instanceID может быть пустым, в таком случае используем '0' (как в steamcommunity)
            $instanceId = $asset['instanceid'] ?? '0';
            $key = $asset['classid'] . '_' . $instanceId;

            if (!isset($descriptionMap[$key])) {
                Log::debug("Description not found for asset", [
                    'asset_id' => $asset['assetid'] ?? 'unknown',
                    'key' => $key,
                    'classid' => $asset['classid'] ?? 'unknown',
                    'instanceid' => $instanceId
                ]);
                continue;
            }

            $description = $descriptionMap[$key];

            // Пропускаем предметы не из CS2
            if (($description['appid'] ?? 0) != self::CS2_APP_ID) {
                continue;
            }

            $item = $this->parseInventoryItem($asset, $description);

            if ($item) {
                $items[] = $item;
            }
        }

        Log::info("Parsed inventory items", [
            'total_assets' => count($assets),
            'total_descriptions' => count($descriptions),
            'parsed_items' => count($items)
        ]);

        return $items;
    }

    private function parseInventoryItem(array $asset, array $description): ?array
    {
        $marketHashName = $description['market_hash_name'] ?? null;
        
        if (!$marketHashName) {
            return null;
        }

        // Убираем состояние из названия для поиска базового предмета
        $baseMarketHashName = $this->removeWearFromMarketHashName($marketHashName);
        
        return [
            'asset_id' => $asset['assetid'],
            'class_id' => $asset['classid'],
            'instance_id' => $asset['instanceid'],
            'amount' => (int) $asset['amount'],
            'item_name' => $baseMarketHashName,
            'market_hash_name' => $marketHashName,
            'name' => $description['name'] ?? $marketHashName,
            'type' => $description['type'] ?? '',
            'icon_url' => $description['icon_url'] ?? null,
            'icon_url_large' => $description['icon_url_large'] ?? null,
            'tradable' => $description['tradable'] ?? 0,
            'marketable' => $description['marketable'] ?? 0,
            'commodity' => $description['commodity'] ?? 0,
            'market_tradable_restriction' => $description['market_tradable_restriction'] ?? 0,
            'descriptions' => $description['descriptions'] ?? [],
            'actions' => $description['actions'] ?? [],
            'tags' => $description['tags'] ?? [],
            'float_value' => null,
            'pattern_index' => null,
            'stickers' => null,
            'inspect_url' => $this->generateInspectUrl($asset, $description, $this->currentSteamId),
        ];
    }


    private function generateInspectUrl(array $asset, array $description, string $steamId = ''): ?string
    {
        $actions = $description['actions'] ?? [];
        
        foreach ($actions as $action) {
            $actionName = $action['name'] ?? '';
            
            // Проверяем разные языки для действия "Inspect in Game"
            if ($actionName === 'Inspect in Game...' || $actionName === 'Осмотреть в игре…') {
                $link = $action['link'] ?? '';
                
                // Заменяем плейсхолдеры на реальные значения
                $link = str_replace('%owner_steamid%', $asset['owner'] ?? $steamId, $link);
                $link = str_replace('%assetid%', $asset['assetid'], $link);
                
                return $link;
            }
        }

        return null;
    }

    public function validateItemOwnership(string $steamId, string $assetId): bool
    {
        $inventory = $this->getInventory($steamId);
        
        foreach ($inventory as $item) {
            if ($item['asset_id'] === $assetId) {
                return true;
            }
        }

        return false;
    }

    public function getInventoryItemByAssetId(string $steamId, string $assetId): ?array
    {
        $inventory = $this->getInventory($steamId);
        
        foreach ($inventory as $item) {
            if ($item['asset_id'] === $assetId) {
                return $item;
            }
        }

        return null;
    }

    public function getInventoryStats(string $steamId): array
    {
        $inventory = $this->getInventory($steamId);
        
        $stats = [
            'total_items' => count($inventory),
            'tradable_items' => 0,
            'marketable_items' => 0,
            'estimated_value' => 0,
        ];

        foreach ($inventory as $item) {
            if ($item['tradable']) {
                $stats['tradable_items']++;
            }
            
            if ($item['marketable']) {
                $stats['marketable_items']++;
            }
            
            // Примерная стоимость теперь берется из SteamMarketItem или других источников
            // TODO: Использовать SteamMarketItem для получения цены
        }

        return $stats;
    }

    /**
     * Убираем состояние износа из market_hash_name для поиска базового предмета
     */
    private function removeWearFromMarketHashName(string $marketHashName): string
    {
        // Список всех возможных состояний в CS2
        $wearStates = [
            ' (Factory New)',
            ' (Minimal Wear)', 
            ' (Field-Tested)',
            ' (Well-Worn)',
            ' (Battle-Scarred)'
        ];
        
        // Убираем состояние, если оно есть
        foreach ($wearStates as $wear) {
            if (str_ends_with($marketHashName, $wear)) {
                return substr($marketHashName, 0, -strlen($wear));
            }
        }
        
        // Если состояния нет, возвращаем как есть
        return $marketHashName;
    }
}