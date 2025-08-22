<?php

namespace App\Services\Steam;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    private const STEAM_INVENTORY_URL = 'https://steamcommunity.com/inventory/%s/730/2?l=russian';
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
            $url = sprintf(self::STEAM_INVENTORY_URL, $steamId);
            
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::warning("Failed to fetch Steam inventory for {$steamId}", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();
            
            if (!isset($data['assets'], $data['descriptions'])) {
                Log::info("Empty or invalid inventory for {$steamId}");
                return [];
            }

            // Логируем статистику инвентаря
            /*
            if (!empty($data['assets']) && !empty($data['descriptions'])) {
                Log::info("Steam inventory stats", [
                    'total_assets' => count($data['assets']),
                    'total_descriptions' => count($data['descriptions']),
                    'sample_items' => array_slice(array_map(function($desc) {
                        return $desc['market_hash_name'] ?? $desc['name'] ?? 'unknown';
                    }, $data['descriptions']), 0, 10)
                ]);
            }
            */
            return $this->parseInventoryData($data);
            
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
        
        // Создаем индекс описаний для быстрого поиска
        $descriptionMap = [];
        foreach ($descriptions as $description) {
            $key = $description['classid'] . '_' . $description['instanceid'];
            $descriptionMap[$key] = $description;
        }

        $items = [];
        
        foreach ($assets as $asset) {
            $key = $asset['classid'] . '_' . $asset['instanceid'];
            
            if (!isset($descriptionMap[$key])) {
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