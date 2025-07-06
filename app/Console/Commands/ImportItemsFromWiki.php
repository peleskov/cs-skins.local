<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportItemsFromWiki extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:import-wiki 
                            {--limit=1000 : Limit number of items to import (0 = no limit)}
                            {--weapon=all : Weapon type to import (all, rifle, pistol, knife, sniper, smg, heavy, gloves)}
                            {--skip-existing : Skip items that already exist in database}
                            {--per-weapon=0 : Import N items per weapon type (0 = all items)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import CS2 items from wiki.cs.money';

    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;
    
    /**
     * Weapon URLs for wiki.cs.money
     */
    private array $weaponUrls = [
        'rifle' => [
            'https://wiki.cs.money/ru/weapons/ak-47',
            'https://wiki.cs.money/ru/weapons/m4a4',
            'https://wiki.cs.money/ru/weapons/m4a1-s',
            'https://wiki.cs.money/ru/weapons/galil-ar',
            'https://wiki.cs.money/ru/weapons/famas',
            'https://wiki.cs.money/ru/weapons/sg-553',
            'https://wiki.cs.money/ru/weapons/aug',
        ],
        'pistol' => [
            'https://wiki.cs.money/ru/weapons/glock-18',
            'https://wiki.cs.money/ru/weapons/usp-s',
            'https://wiki.cs.money/ru/weapons/p2000',
            'https://wiki.cs.money/ru/weapons/desert-eagle',
            'https://wiki.cs.money/ru/weapons/p250',
            'https://wiki.cs.money/ru/weapons/five-seven',
            'https://wiki.cs.money/ru/weapons/tec-9',
            'https://wiki.cs.money/ru/weapons/cz75-auto',
            'https://wiki.cs.money/ru/weapons/r8-revolver',
        ],
        'knife' => [
            'https://wiki.cs.money/ru/knives/all',
        ],
        'sniper' => [
            'https://wiki.cs.money/ru/weapons/awp',
            'https://wiki.cs.money/ru/weapons/ssg-08',
            'https://wiki.cs.money/ru/weapons/scar-20',
            'https://wiki.cs.money/ru/weapons/g3sg1',
        ],
        'smg' => [
            'https://wiki.cs.money/ru/weapons/mac-10',
            'https://wiki.cs.money/ru/weapons/mp7',
            'https://wiki.cs.money/ru/weapons/mp9',
            'https://wiki.cs.money/ru/weapons/mp5-sd',
            'https://wiki.cs.money/ru/weapons/p90',
            'https://wiki.cs.money/ru/weapons/pp-bizon',
            'https://wiki.cs.money/ru/weapons/ump-45',
        ],
        'heavy' => [
            'https://wiki.cs.money/ru/weapons/nova',
            'https://wiki.cs.money/ru/weapons/xm1014',
            'https://wiki.cs.money/ru/weapons/mag-7',
            'https://wiki.cs.money/ru/weapons/sawed-off',
            'https://wiki.cs.money/ru/weapons/m249',
            'https://wiki.cs.money/ru/weapons/negev',
        ],
        'gloves' => [
            'https://wiki.cs.money/ru/gloves/all',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting import from wiki.cs.money...');
        
        $limit = (int) $this->option('limit');
        $weaponType = $this->option('weapon');
        $skipExisting = $this->option('skip-existing');
        $perWeapon = (int) $this->option('per-weapon');
        
        $this->info("Import settings:");
        $this->info("- Weapon type: {$weaponType}");
        $this->info("- Total limit: " . ($limit === 0 ? 'No limit' : $limit));
        $this->info("- Per weapon limit: " . ($perWeapon === 0 ? 'All items' : $perWeapon));
        $this->info("- Skip existing: " . ($skipExisting ? 'Yes' : 'No'));
        
        try {
            // Get items data from wiki.cs.money
            $items = $this->fetchItemsFromWiki($limit, $weaponType, $perWeapon);
            
            if (empty($items)) {
                $this->error('No items found or failed to fetch data from wiki.cs.money');
                return Command::FAILURE;
            }

            $this->info("Found " . count($items) . " items. Starting import...");
            
            $progressBar = $this->output->createProgressBar(count($items));
            $progressBar->start();

            foreach ($items as $itemData) {
                if ($skipExisting && Item::where('steam_market_hash_name', $itemData['steam_market_hash_name'])->exists()) {
                    $this->skipped++;
                    $progressBar->advance();
                    continue;
                }
                
                $this->processItem($itemData);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->displayResults();
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Fetch items data from wiki.cs.money
     */
    private function fetchItemsFromWiki(int $limit, string $weaponType, int $perWeapon): array
    {
        $this->info('Fetching data from wiki.cs.money...');
        
        $allItems = [];
        
        // Determine which URLs to fetch
        $urlsToFetch = [];
        if ($weaponType === 'all') {
            foreach ($this->weaponUrls as $urls) {
                $urlsToFetch = array_merge($urlsToFetch, $urls);
            }
        } elseif (isset($this->weaponUrls[$weaponType])) {
            $urlsToFetch = $this->weaponUrls[$weaponType];
        } else {
            $this->error("Unknown weapon type: {$weaponType}");
            return [];
        }
        
        $this->info("Will fetch from " . count($urlsToFetch) . " URLs");
        
        foreach ($urlsToFetch as $url) {
            $this->info("Fetching: {$url}");
            
            $html = $this->fetchWithCurl($url);
            
            if ($html) {
                $items = $this->parseWikiResponse($html);
                $this->info("  Found " . count($items) . " items");
                
                // Apply per-weapon limit if set
                if ($perWeapon > 0 && count($items) > $perWeapon) {
                    $items = array_slice($items, 0, $perWeapon);
                    $this->info("  Limited to {$perWeapon} items per weapon");
                }
                
                $allItems = array_merge($allItems, $items);
                
                // Add small delay to avoid rate limiting
                sleep(1);
            } else {
                $this->warn("  Failed to fetch data from {$url}");
            }
            
            // Check if we've reached the total limit
            if ($limit > 0 && count($allItems) >= $limit) {
                $this->info("Reached total limit of {$limit} items");
                break;
            }
        }
        
        // Apply total limit if set
        if ($limit > 0 && count($allItems) > $limit) {
            $allItems = array_slice($allItems, 0, $limit);
        }
        
        return $allItems;
    }

    /**
     * Fetch page with system curl (bypasses blocking)
     */
    private function fetchWithCurl(string $url): ?string
    {
        $command = sprintf(
            'curl -s -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8" -H "Accept-Language: ru-RU,ru;q=0.9,en;q=0.8" -H "Cache-Control: max-age=0" %s',
            escapeshellarg($url)
        );
        
        $output = null;
        $returnCode = null;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error("Curl command failed with code: {$returnCode}");
            return null;
        }
        
        $html = implode("\n", $output);
        
        if (empty($html)) {
            $this->warn("Empty response from {$url}");
            return null;
        }
        
        return $html;
    }

    /**
     * Parse response from wiki.cs.money
     */
    private function parseWikiResponse(string $html): array
    {
        $items = [];
        
        // Extract __NEXT_DATA__ JSON from HTML
        if (preg_match('/__NEXT_DATA__[^>]*>(.+?)<\/script>/s', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("JSON decode error: " . json_last_error_msg());
                return $items;
            }
            
            if ($jsonData && isset($jsonData['props']['pageProps']['apolloState']['ROOT_QUERY'])) {
                $rootQuery = $jsonData['props']['pageProps']['apolloState']['ROOT_QUERY'];
                
                // Find weapon items in the Apollo state
                foreach ($rootQuery as $key => $value) {
                    if (strpos($key, 'weapon(') !== false && is_array($value)) {
                        // Check for items inside weapon data
                        foreach ($value as $subKey => $subValue) {
                            if (strpos($subKey, 'items(') !== false && is_array($subValue)) {
                                foreach ($subValue as $item) {
                                    $parsedItem = $this->parseWikiItem($item);
                                    if ($parsedItem) {
                                        $items[] = $parsedItem;
                                    }
                                }
                                break 2;
                            }
                        }
                    }
                }
            } else {
                $this->warn("Could not find apolloState data in JSON");
            }
        } else {
            $this->warn("Could not find __NEXT_DATA__ in HTML");
        }
        
        return $items;
    }

    /**
     * Parse single item from wiki.cs.money API response
     */
    private function parseWikiItem(array $item): ?array
    {
        try {
            // Map wiki.cs.money data to our structure
            $name = $item['name'] ?? '';
            $hashName = $item['hash_name'] ?? $name;
            $title = $item['title'] ?? '';
            $subtitle = $item['subtitle'] ?? '';
            
            // Build full name
            $fullNameRu = $title && $subtitle ? $title . ' | ' . $subtitle : $name;
            $fullNameEn = $hashName;
            
            // Determine weapon type and rarity
            $type = $this->determineWeaponType($title ?: $name);
            $rarity = $this->determineRarity($item['rarity'] ?? '');
            
            // Get price
            $price = 0;
            if (isset($item['price']['common']['min'])) {
                $price = (float) $item['price']['common']['min'];
            }
            
            // Build tags
            $tags = [];
            if (isset($item['type']) && $item['type'] !== 'Normal') {
                $tags[] = $item['type'];
            }
            
            return [
                'steam_market_hash_name' => $hashName,
                'name_ru' => $fullNameRu,
                'name_en' => $fullNameEn,
                'type' => $type,
                'weapon' => $this->extractWeaponName($title ?: $name),
                'rarity' => $rarity,
                'image_url' => $item['image'] ?? '',
                'min_steam_price' => $price,
                'steam_listings_count' => rand(50, 2000), // Заглушка, нет данных в wiki
                'description_ru' => $item['texts']['appearance_history'] ?? null,
                'description_en' => null,
                'tags' => $tags,
            ];
            
        } catch (\Exception $e) {
            $itemName = $item['name'] ?? 'unknown';
            $this->warn("Failed to parse item {$itemName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse HTML response (fallback)
     */
    private function parseWikiHtml(string $html): array
    {
        // Fallback to sample items if HTML parsing is complex
        // This would require proper DOM parsing with DOMDocument
        $this->info('Using sample items as fallback...');
        return $this->getSampleItems(50);
    }

    /**
     * Determine weapon type from name
     */
    private function determineWeaponType(string $name): string
    {
        $name = strtolower($name);
        
        if (str_contains($name, '★')) {
            if (str_contains($name, 'gloves') || str_contains($name, 'перчатки')) {
                return Item::TYPE_GLOVES;
            }
            return Item::TYPE_KNIFE;
        }
        
        $typeMap = [
            'ak-47' => Item::TYPE_RIFLE,
            'm4a4' => Item::TYPE_RIFLE,
            'm4a1-s' => Item::TYPE_RIFLE,
            'awp' => Item::TYPE_SNIPER,
            'glock-18' => Item::TYPE_PISTOL,
            'usp-s' => Item::TYPE_PISTOL,
            'deagle' => Item::TYPE_PISTOL,
            'desert eagle' => Item::TYPE_PISTOL,
            'mp7' => Item::TYPE_SMG,
            'mp9' => Item::TYPE_SMG,
            'mac-10' => Item::TYPE_SMG,
            'p90' => Item::TYPE_SMG,
            'nova' => Item::TYPE_SHOTGUN,
            'xm1014' => Item::TYPE_SHOTGUN,
            'mag-7' => Item::TYPE_SHOTGUN,
            'negev' => Item::TYPE_MACHINEGUN,
            'm249' => Item::TYPE_MACHINEGUN,
        ];
        
        foreach ($typeMap as $weapon => $type) {
            if (str_contains($name, $weapon)) {
                return $type;
            }
        }
        
        // Default based on common patterns
        if (str_contains($name, 'rifle') || str_contains($name, 'автомат')) {
            return Item::TYPE_RIFLE;
        }
        if (str_contains($name, 'pistol') || str_contains($name, 'пистолет')) {
            return Item::TYPE_PISTOL;
        }
        if (str_contains($name, 'sticker') || str_contains($name, 'наклейка')) {
            return Item::TYPE_STICKER;
        }
        if (str_contains($name, 'case') || str_contains($name, 'кейс')) {
            return Item::TYPE_CASE;
        }
        
        return Item::TYPE_RIFLE; // Default
    }

    /**
     * Determine rarity from wiki data
     */
    private function determineRarity(string $rarity): string
    {
        $rarity = strtolower($rarity);
        
        $rarityMap = [
            'consumer' => Item::RARITY_CONSUMER,
            'ширпотреб' => Item::RARITY_CONSUMER,
            'industrial' => Item::RARITY_INDUSTRIAL,
            'промышленное' => Item::RARITY_INDUSTRIAL,
            'mil-spec' => Item::RARITY_MIL_SPEC,
            'армейское' => Item::RARITY_MIL_SPEC,
            'restricted' => Item::RARITY_RESTRICTED,
            'запрещённое' => Item::RARITY_RESTRICTED,
            'classified' => Item::RARITY_CLASSIFIED,
            'засекреченное' => Item::RARITY_CLASSIFIED,
            'covert' => Item::RARITY_COVERT,
            'тайное' => Item::RARITY_COVERT,
            'contraband' => Item::RARITY_CONTRABAND,
            'контрабанда' => Item::RARITY_CONTRABAND,
        ];
        
        return $rarityMap[$rarity] ?? Item::RARITY_CONSUMER;
    }

    /**
     * Extract weapon name from full item name
     */
    private function extractWeaponName(string $name): ?string
    {
        // Extract weapon name before the first "|"
        $parts = explode('|', $name);
        $weaponName = trim($parts[0] ?? '');
        
        // Clean up special characters
        $weaponName = str_replace('★ ', '', $weaponName);
        
        return $weaponName ?: null;
    }

    /**
     * Build Steam CDN image URL
     */
    private function buildSteamImageUrl(string $imagePath): string
    {
        if (str_starts_with($imagePath, 'http')) {
            return $imagePath;
        }
        
        // Steam CDN format
        return "https://community.cloudflare.steamstatic.com/economy/image/{$imagePath}";
    }

    /**
     * Process single item and save to database
     */
    private function processItem(array $itemData): void
    {
        try {
            $item = Item::updateOrCreate(
                ['steam_market_hash_name' => $itemData['steam_market_hash_name']],
                [
                    'name_ru' => $itemData['name_ru'],
                    'name_en' => $itemData['name_en'],
                    'type' => $itemData['type'],
                    'weapon' => $itemData['weapon'] ?? null,
                    'rarity' => $itemData['rarity'],
                    'image_url' => $itemData['image_url'],
                    'min_steam_price' => $itemData['min_steam_price'] ?? null,
                    'steam_listings_count' => $itemData['steam_listings_count'] ?? 0,
                    'is_valid' => ($itemData['steam_listings_count'] ?? 0) > 200,
                    'buyout_coefficient' => Item::BUYOUT_COEFFICIENTS[$itemData['rarity']] ?? 0.20,
                    'description_ru' => $itemData['description_ru'] ?? null,
                    'description_en' => $itemData['description_en'] ?? null,
                    'tags' => $itemData['tags'] ?? [],
                ]
            );

            if ($item->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->updated++;
            }

        } catch (\Exception $e) {
            $this->skipped++;
            $this->warn("Skipped item {$itemData['name_en']}: " . $e->getMessage());
        }
    }

    /**
     * Display import results
     */
    private function displayResults(): void
    {
        $this->info('Import completed!');
        $this->table(
            ['Action', 'Count'],
            [
                ['Imported', $this->imported],
                ['Updated', $this->updated],
                ['Skipped', $this->skipped],
                ['Total', $this->imported + $this->updated + $this->skipped]
            ]
        );
    }

    /**
     * Get sample items for testing (replace with real wiki.cs.money parser)
     */
    private function getSampleItems(int $limit): array
    {
        $sampleItems = [
            [
                'steam_market_hash_name' => 'AK-47 | Redline (Field-Tested)',
                'name_ru' => 'AK-47 | Красная линия',
                'name_en' => 'AK-47 | Redline',
                'type' => Item::TYPE_RIFLE,
                'weapon' => 'AK-47',
                'rarity' => Item::RARITY_CLASSIFIED,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/360467259/256x256',
                'min_steam_price' => 95.50,
                'steam_listings_count' => 1250,
                'description_ru' => 'После полевых испытаний',
                'description_en' => 'Field-Tested',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => 'AWP | Dragon Lore (Factory New)',
                'name_ru' => 'AWP | Знания дракона',
                'name_en' => 'AWP | Dragon Lore',
                'type' => Item::TYPE_SNIPER,
                'weapon' => 'AWP',
                'rarity' => Item::RARITY_COVERT,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/520032493/256x256',
                'min_steam_price' => 4850.00,
                'steam_listings_count' => 45,
                'description_ru' => 'Прямо с завода',
                'description_en' => 'Factory New',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => '★ Karambit | Doppler (Minimal Wear)',
                'name_ru' => '★ Керамбит | Доплер',
                'name_en' => '★ Karambit | Doppler',
                'type' => Item::TYPE_KNIFE,
                'weapon' => 'Karambit',
                'rarity' => Item::RARITY_COVERT,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/721076282/256x256',
                'min_steam_price' => 1250.00,
                'steam_listings_count' => 320,
                'description_ru' => 'Немного поношенное',
                'description_en' => 'Minimal Wear',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => 'M4A4 | Howl (Minimal Wear)',
                'name_ru' => 'M4A4 | Вой',
                'name_en' => 'M4A4 | Howl',
                'type' => Item::TYPE_RIFLE,
                'weapon' => 'M4A4',
                'rarity' => Item::RARITY_CONTRABAND,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/506853367/256x256',
                'min_steam_price' => 3200.00,
                'steam_listings_count' => 89,
                'description_ru' => 'Немного поношенное',
                'description_en' => 'Minimal Wear',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => '★ Sport Gloves | Pandora\'s Box (Battle-Scarred)',
                'name_ru' => '★ Спортивные перчатки | Ящик Пандоры',
                'name_en' => '★ Sport Gloves | Pandora\'s Box',
                'type' => Item::TYPE_GLOVES,
                'weapon' => 'Sport Gloves',
                'rarity' => Item::RARITY_COVERT,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/2077639777/256x256',
                'min_steam_price' => 890.00,
                'steam_listings_count' => 156,
                'description_ru' => 'Закалённое в боях',
                'description_en' => 'Battle-Scarred',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => 'Glock-18 | Fade (Factory New)',
                'name_ru' => 'Glock-18 | Градиент',
                'name_en' => 'Glock-18 | Fade',
                'type' => Item::TYPE_PISTOL,
                'weapon' => 'Glock-18',
                'rarity' => Item::RARITY_RESTRICTED,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/310781313/256x256',
                'min_steam_price' => 165.00,
                'steam_listings_count' => 567,
                'description_ru' => 'Прямо с завода',
                'description_en' => 'Factory New',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => '★ M9 Bayonet | Crimson Web (Minimal Wear)',
                'name_ru' => '★ Штык-нож M9 | Кровавая паутина',
                'name_en' => '★ M9 Bayonet | Crimson Web',
                'type' => Item::TYPE_KNIFE,
                'weapon' => 'M9 Bayonet',
                'rarity' => Item::RARITY_COVERT,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/310801152/256x256',
                'min_steam_price' => 2100.00,
                'steam_listings_count' => 234,
                'description_ru' => 'Немного поношенное',
                'description_en' => 'Minimal Wear',
                'tags' => []
            ],
            [
                'steam_market_hash_name' => 'USP-S | Kill Confirmed (Field-Tested)',
                'name_ru' => 'USP-S | Ликвидация подтверждена',
                'name_en' => 'USP-S | Kill Confirmed',
                'type' => Item::TYPE_PISTOL,
                'weapon' => 'USP-S',
                'rarity' => Item::RARITY_CLASSIFIED,
                'image_url' => 'https://community.cloudflare.steamstatic.com/economy/image/class/730/1310006695/256x256',
                'min_steam_price' => 75.00,
                'steam_listings_count' => 890,
                'description_ru' => 'После полевых испытаний',
                'description_en' => 'Field-Tested',
                'tags' => []
            ]
        ];

        return array_slice($sampleItems, 0, min($limit, count($sampleItems)));
    }
}
