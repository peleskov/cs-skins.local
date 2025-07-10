<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SteamInventoryService
{
    private const STEAM_INVENTORY_URL = 'https://steamcommunity.com/inventory/%s/730/2';
    private const CS2_APP_ID = 730;
    private const CACHE_DURATION = 600; // 10 минут

    public function getInventory(string $steamId): array
    {
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
            if (!empty($data['assets']) && !empty($data['descriptions'])) {
                Log::info("Steam inventory stats", [
                    'total_assets' => count($data['assets']),
                    'total_descriptions' => count($data['descriptions']),
                    'sample_items' => array_slice(array_map(function($desc) {
                        return $desc['market_hash_name'] ?? $desc['name'] ?? 'unknown';
                    }, $data['descriptions']), 0, 10)
                ]);
            }

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
        
        // Находим предмет в нашей базе данных (опционально)
        $item = Item::where('steam_market_hash_name', $baseMarketHashName)->first();
        
        // Логируем информацию о ненайденных предметах
        if (!$item) {
            Log::info("Item not found in database - saving anyway", [
                'original_market_hash_name' => $marketHashName,
                'base_market_hash_name' => $baseMarketHashName,
                'classid' => $asset['classid'],
                'instanceid' => $asset['instanceid'],
                'description_name' => $description['name'] ?? null,
                'description_type' => $description['type'] ?? null,
                'appid' => $description['appid'] ?? null,
                'tags' => $description['tags'] ?? []
            ]);
        }

        return [
            'asset_id' => $asset['assetid'],
            'class_id' => $asset['classid'],
            'instance_id' => $asset['instanceid'],
            'amount' => (int) $asset['amount'],
            'item_id' => $item?->id, // Может быть null
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
            'float_value' => $this->extractFloatValue($description),
            'pattern_index' => $this->extractPatternIndex($description),
            'stickers' => $this->extractStickers($description),
            'inspect_url' => $this->generateInspectUrl($asset, $description),
        ];
    }

    private function extractFloatValue(array $description): ?float
    {
        // Извлекаем float из описаний предмета
        $descriptions = $description['descriptions'] ?? [];
        
        foreach ($descriptions as $desc) {
            $value = $desc['value'] ?? '';
            
            // Ищем паттерн float значения
            if (preg_match('/Float Value: ([0-9.]+)/', $value, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    private function extractPatternIndex(array $description): ?int
    {
        // Извлекаем pattern index из описаний предмета
        $descriptions = $description['descriptions'] ?? [];
        
        foreach ($descriptions as $desc) {
            $value = $desc['value'] ?? '';
            
            // Ищем паттерн pattern index
            if (preg_match('/Pattern: ([0-9]+)/', $value, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function extractStickers(array $description): array
    {
        $stickers = [];
        $descriptions = $description['descriptions'] ?? [];
        
        foreach ($descriptions as $desc) {
            $value = $desc['value'] ?? '';
            
            // Ищем стикеры
            if (strpos($value, 'Sticker:') !== false) {
                // Простая обработка стикеров
                $stickers[] = strip_tags($value);
            }
        }

        return $stickers;
    }

    private function generateInspectUrl(array $asset, array $description): ?string
    {
        $actions = $description['actions'] ?? [];
        
        foreach ($actions as $action) {
            if (isset($action['name']) && $action['name'] === 'Inspect in Game...') {
                $link = $action['link'] ?? '';
                
                // Заменяем плейсхолдеры на реальные значения
                $link = str_replace('%owner_steamid%', $asset['owner'] ?? '', $link);
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
            
            // Добавляем примерную стоимость на основе данных из нашей базы
            if ($item['item_id']) {
                $dbItem = Item::find($item['item_id']);
                if ($dbItem && $dbItem->min_steam_price) {
                    $stats['estimated_value'] += $dbItem->min_steam_price;
                }
            }
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