<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\SteamInventoryService;
use Illuminate\Console\Command;

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
            $inventoryService = app(SteamInventoryService::class);
            
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

        // Сохраняем новый инвентарь
        foreach ($inventory as $item) {
            $client->inventoryItems()->create([
                'steam_asset_id' => $item['asset_id'],
                'steam_class_id' => $item['class_id'],
                'steam_instance_id' => $item['instance_id'],
                'market_hash_name' => $item['market_hash_name'],
                'item_name' => $item['item_name'] ?? $item['name'] ?? '',
                'type' => $item['type'] ?? null,
                'icon_url' => $item['icon_url'],
                'tradable' => $item['tradable'],
                'marketable' => $item['marketable'],
                'amount' => $item['amount'] ?? 1,
                'float_value' => $item['float_value'],
                'pattern_index' => $item['pattern_index'],
                'stickers' => $item['stickers'] ? json_encode($item['stickers']) : null,
                'inspect_url' => $item['inspect_url'],
                'tags' => $item['tags'] ? json_encode($item['tags']) : null,
                'descriptions' => $item['descriptions'] ? json_encode($item['descriptions']) : null,
                'item_id' => $item['item_id'],
                'cached_at' => now(),
            ]);
        }
    }
}
