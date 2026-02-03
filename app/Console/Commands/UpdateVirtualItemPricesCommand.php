<?php

namespace App\Console\Commands;

use App\Models\VirtualItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateVirtualItemPricesCommand extends Command
{
    protected $signature = 'virtual-items:update-prices
                            {--limit=100 : Количество предметов для обновления}
                            {--delay=1000 : Задержка между запросами в мс}
                            {--all : Обновить все, игнорируя лимит}';

    protected $description = 'Обновление цен виртуальных предметов из Steam Market';

    private const STEAM_API_URL = 'https://steamcommunity.com/market/priceoverview/';

    public function handle(): int
    {
        $limit = $this->option('all') ? null : (int) $this->option('limit');
        $delay = (int) $this->option('delay');

        // Получаем предметы без цены или с устаревшей ценой
        $query = VirtualItem::query()
            ->whereNotNull('market_hash_name')
            ->where(function ($q) {
                $q->whereNull('steam_price')
                  ->orWhere('updated_at', '<', now()->subDay());
            })
            ->orderBy('steam_price', 'asc'); // Сначала те, у кого нет цены

        if ($limit) {
            $query->limit($limit);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('Нет предметов для обновления цен');
            return 0;
        }

        $this->info("Обновление цен для {$items->count()} предметов...");

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $updated = 0;
        $failed = 0;
        $noPrice = 0;

        foreach ($items as $item) {
            try {
                $price = $this->fetchPrice($item->market_hash_name);

                if ($price !== null) {
                    $item->update(['steam_price' => $price]);
                    $updated++;
                } else {
                    $noPrice++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::warning('Failed to fetch Steam price', [
                    'market_hash_name' => $item->market_hash_name,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();

            // Задержка между запросами
            usleep($delay * 1000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Обновлено: {$updated}");
        $this->info("Нет на маркете: {$noPrice}");
        $this->info("Ошибок: {$failed}");

        return 0;
    }

    private function fetchPrice(string $marketHashName): ?float
    {
        $response = Http::timeout(10)->get(self::STEAM_API_URL, [
            'appid' => 730,
            'currency' => 1, // USD
            'market_hash_name' => $marketHashName,
        ]);

        if (!$response->successful()) {
            throw new \Exception('HTTP ' . $response->status());
        }

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            return null;
        }

        // Парсим цену из формата "$123.45" или "123,45€"
        $priceStr = $data['lowest_price'] ?? $data['median_price'] ?? null;

        if (!$priceStr) {
            return null;
        }

        // Убираем символы валюты и форматирование
        $price = preg_replace('/[^0-9.,]/', '', $priceStr);
        $price = str_replace(',', '.', $price);

        return (float) $price ?: null;
    }
}
