<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientInventoryItem;
use App\Services\Steam\InventoryService;
use App\Jobs\FetchSteamPriceHistory;
use App\Jobs\FetchFloatValueJob;
use App\Models\SteamMarketItem;
use App\Models\Tag;
use App\Models\MarketItemTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync {steam_id?} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизация инвентаря пользователя из Steam API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $steamId = $this->argument('steam_id');
        $force = $this->option('force');

        if ($steamId) {
            // Синхронизация конкретного пользователя
            $this->syncUserInventory($steamId, $force);
        } else {
            // Синхронизация всех пользователей
            $this->syncAllUsersInventory($force);
        }
    }

    private function syncUserInventory(string $steamId, bool $force = false): void
    {
        $client = Client::where('steam_id', $steamId)->first();
        
        if (!$client) {
            $this->error("Клиент с Steam ID {$steamId} не найден");
            return;
        }

        $this->info("Синхронизация инвентаря для {$client->name} ({$steamId})");

        try {
            $inventoryService = app(InventoryService::class);
            
            if ($force) {
                $inventory = $inventoryService->refreshInventory($steamId);
            } else {
                $inventory = $inventoryService->getInventory($steamId);
            }

            $this->info("Загружено предметов: " . count($inventory));
            
            // Сохраняем в базу данных
            $this->saveInventoryToDatabase($client, $inventory);
            
            $this->info("✅ Инвентарь успешно синхронизирован");
            
        } catch (\Exception $e) {
            $this->error("❌ Ошибка синхронизации: " . $e->getMessage());
        }
    }

    private function syncAllUsersInventory(bool $force = false): void
    {
        $clients = Client::whereNotNull('steam_id')->get();
        
        $this->info("Синхронизация инвентаря для " . $clients->count() . " пользователей");

        $progressBar = $this->output->createProgressBar($clients->count());
        $progressBar->start();

        foreach ($clients as $client) {
            $this->syncUserInventory($client->steam_id, $force);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Синхронизация завершена");
    }

    private function saveInventoryToDatabase(Client $client, array $inventory): void
    {
        // Очищаем старый кеш инвентаря
        $client->inventoryItems()->delete();

        $this->info("Saving " . count($inventory) . " items to database");

        $processedMarketHashNames = [];
        $jobDelay = 0;
        $floatJobsCreated = 0;

        // Сохраняем новый инвентарь
        foreach ($inventory as $item) {
            // Получаем item_nameid из SteamMarketItem если есть
            $steamMarketItem = SteamMarketItem::where('market_hash_name', $item['market_hash_name'])->first();
            $itemNameid = $steamMarketItem?->item_nameid;
            $itemNameidFetchedAt = $steamMarketItem && $steamMarketItem->item_nameid ? now() : null;

            $inventoryItem = $client->inventoryItems()->create([
                'steam_asset_id' => $item['asset_id'],
                'steam_class_id' => $item['class_id'],
                'steam_instance_id' => $item['instance_id'],
                'market_hash_name' => $item['market_hash_name'],
                'item_name' => $item['item_name'] ?? $item['name'] ?? '',
                'icon_url' => $item['icon_url'],
                'tradable' => $item['tradable'],
                'marketable' => $item['marketable'],
                'amount' => $item['amount'] ?? 1,
                'float_value' => $item['float_value'],
                'pattern_index' => $item['pattern_index'],
                'stickers' => $item['stickers'] ? json_encode($item['stickers']) : null,
                'inspect_url' => $item['inspect_url'],
                'descriptions' => $item['descriptions'] ? json_encode($item['descriptions']) : null,
                'cached_at' => now(),
                'item_nameid' => $itemNameid,
                'item_nameid_fetched_at' => $itemNameidFetchedAt,
            ]);

            // Парсим и сохраняем теги в новой системе
            $this->parseAndSaveTags($inventoryItem, $item['tags'] ?? []);

            // Создаем Job для получения float данных
            if ($this->shouldFetchFloatData($item)) {
                FetchFloatValueJob::dispatch($inventoryItem)
                    ->delay(now()->addSeconds($floatJobsCreated * 5))
                    ->onQueue('float-values');
                
                $floatJobsCreated++;
            }

            // Запускаем job для получения истории цен только для торгуемых предметов
            if ($item['market_hash_name'] && $item['tradable'] == 1 && $item['marketable'] == 1 && !in_array($item['market_hash_name'], $processedMarketHashNames)) {
                try {
                    // Проверяем нужно ли обновление
                    $marketItem = SteamMarketItem::where('market_hash_name', $item['market_hash_name'])->first();
                    
                    if (!$marketItem || !$marketItem->item_nameid || $marketItem->needsPriceUpdate()) {
                        // Запускаем job с задержкой для избежания rate limit
                        FetchSteamPriceHistory::dispatch($item['market_hash_name'])
                            ->delay(now()->addSeconds($jobDelay * 30))
                            ->onQueue('default');
                        
                        $jobDelay++;
                    }
                    
                    $processedMarketHashNames[] = $item['market_hash_name'];
                } catch (\Exception $e) {
                    \Log::error("Failed to dispatch FetchSteamPriceHistory job", [
                        'market_hash_name' => $item['market_hash_name'], 
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($jobDelay > 0) {
            $this->info("  📈 Запланировано {$jobDelay} обновлений истории цен");
        }
        
        if ($floatJobsCreated > 0) {
            $this->info("  🎯 Запланировано {$floatJobsCreated} получений float данных");
        }
    }

    /**
     * Парсит Steam теги и сохраняет в новой системе тегов через market_hash_name
     */
    private function parseAndSaveTags(ClientInventoryItem $inventoryItem, array $steamTags): void
    {
        if (empty($steamTags)) {
            return;
        }

        $tagIds = [];

        foreach ($steamTags as $steamTag) {
            if (!isset($steamTag['internal_name']) || !isset($steamTag['category'])) {
                continue;
            }

            $categoryCode = $this->mapSteamCategoryToCode($steamTag['category']);
            if (!$categoryCode) {
                continue;
            }

            // Получаем или создаем тег в новой системе
            $tag = $this->getOrCreateTag($categoryCode, $steamTag);
            
            if ($tag) {
                $tagIds[] = $tag->id;
            }
        }

        // Синхронизируем теги для market_hash_name (только один раз на market_hash_name)
        if (!empty($tagIds) && $inventoryItem->market_hash_name) {
            MarketItemTag::syncTagsForMarketItem($inventoryItem->market_hash_name, $tagIds);
        }
    }

    /**
     * Маппинг Steam категорий в наши коды
     */
    private function mapSteamCategoryToCode(string $steamCategory): ?string
    {
        return match($steamCategory) {
            'Type' => 'type',
            'Quality' => 'quality',
            'Rarity' => 'rarity',
            'Exterior' => 'exterior',
            'Weapon' => 'weapon',
            'ItemSet' => 'collection',
            'Tournament' => 'tournament',
            'TournamentTeam' => 'team',
            'StickerCategory' => 'sticker_category',
            'StickerCapsule' => 'sticker_capsule',
            default => null,
        };
    }


    /**
     * Получает или создает тег в новой системе
     */
    private function getOrCreateTag(string $categoryCode, array $steamTag): ?Tag
    {
        $normalizedValue = $this->normalizeTagValue($categoryCode, $steamTag['internal_name']);
        
        // Проверяем существует ли тег
        $existingTag = Tag::where('category_code', $categoryCode)
            ->where('steam_internal_name', $steamTag['internal_name'])
            ->first();

        if ($existingTag) {
            return $existingTag;
        }

        // Создаем новый тег
        return Tag::create([
            'category_code' => $categoryCode,
            'steam_internal_name' => $steamTag['internal_name'],
            'normalized_value' => $normalizedValue,
            'sort_order' => 0,
        ]);
    }

    /**
     * Нормализует значение тега для удобства использования
     */
    private function normalizeTagValue(string $categoryCode, string $steamInternalName): string
    {
        // Специальные маппинги для разных категорий
        switch ($categoryCode) {
            case 'type':
                return match($steamInternalName) {
                    'CSGO_Type_Rifle' => 'rifle',
                    'CSGO_Type_Pistol' => 'pistol',
                    'CSGO_Type_SMG' => 'smg',
                    'CSGO_Type_SniperRifle' => 'sniper',
                    'CSGO_Type_Shotgun' => 'shotgun',
                    'CSGO_Type_Machinegun' => 'machinegun',
                    'CSGO_Type_Knife' => 'knife',
                    'CSGO_Type_Hands' => 'gloves',
                    'CSGO_Tool_Sticker' => 'sticker',
                    'CSGO_Tool_WeaponCase' => 'case',
                    'CSGO_Tool_WeaponCaseKey' => 'key',
                    'CSGO_Tool_Spray' => 'graffiti',
                    default => strtolower(str_replace(['CSGO_Type_', 'CSGO_Tool_'], '', $steamInternalName))
                };
            
            case 'quality':
                return match($steamInternalName) {
                    'normal' => 'normal',
                    'strange' => 'stattrak',
                    'tournament' => 'souvenir',
                    default => strtolower($steamInternalName)
                };
            
            case 'exterior':
                return match($steamInternalName) {
                    'WearCategory0' => 'fn',
                    'WearCategory1' => 'mw',
                    'WearCategory2' => 'ft',
                    'WearCategory3' => 'ww',
                    'WearCategory4' => 'bs',
                    default => strtolower($steamInternalName)
                };
            
            case 'rarity':
                return match($steamInternalName) {
                    'Rarity_Common', 'Rarity_Common_Weapon' => 'consumer',
                    'Rarity_Uncommon', 'Rarity_Uncommon_Weapon' => 'industrial',
                    'Rarity_Rare', 'Rarity_Rare_Weapon' => 'milspec',
                    'Rarity_Mythical', 'Rarity_Mythical_Weapon' => 'restricted',
                    'Rarity_Legendary', 'Rarity_Legendary_Weapon' => 'classified',
                    'Rarity_Ancient', 'Rarity_Ancient_Weapon' => 'covert',
                    'Rarity_Contraband' => 'contraband',
                    default => strtolower(str_replace(['Rarity_', '_Weapon'], '', $steamInternalName))
                };
            
            default:
                // Для остальных категорий просто нормализуем имя
                return strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $steamInternalName));
        }
    }

    /**
     * Определяет нужно ли получать float данные для предмета
     */
    private function shouldFetchFloatData(array $item): bool
    {
        // Получаем float данные только для:
        // 1. Торгуемых и маркетируемых предметов
        // 2. Предметов с inspect_url
        // 3. Исключаем дешевые предметы (стикеры, граффити и т.д.)
        
        if (!($item['tradable'] ?? false) || !($item['marketable'] ?? false)) {
            return false;
        }

        if (empty($item['inspect_url'])) {
            return false;
        }

        // Исключаем предметы которые точно не имеют float
        $excludeTypes = [
            'CSGO_Tool_Sticker',
            'CSGO_Tool_Spray', 
            'CSGO_Tool_WeaponCase',
            'CSGO_Tool_WeaponCaseKey',
            'CSGO_Type_MusicKit',
            'CSGO_Type_Agent',
            'CSGO_Type_Pass',
            'CSGO_Tool_StickerCapsule',
            'CSGO_Tool_GraffitiCapsule',
            'CSGO_Tool_Pin',
            'CSGO_Tool_Patch'
        ];
        
        // Также исключаем по названию рынка (дополнительная проверка)
        $marketHashName = strtolower($item['market_hash_name'] ?? '');
        $excludePatterns = [
            'sticker |',
            'graffiti |',
            'music kit |',
            'case |',
            'capsule |',
            'key |',
            'patch |',
            'pin |'
        ];
        
        foreach ($excludePatterns as $pattern) {
            if (str_contains($marketHashName, $pattern)) {
                return false;
            }
        }

        $tags = $item['tags'] ?? [];
        foreach ($tags as $tag) {
            if (isset($tag['internal_name']) && in_array($tag['internal_name'], $excludeTypes)) {
                return false;
            }
        }

        return true;
    }
}
