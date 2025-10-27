<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Jobs\FetchSteamPriceHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdatePricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновление цен Steam предметов по расписанию';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начинаем обновление цен Steam предметов...');

        // 1. Получаем активные листинги, которые нужно обновить
        $marketHashNames = $this->getItemsToUpdate();

        if ($marketHashNames->isEmpty()) {
            $this->info('Нет предметов для обновления цен');
            return;
        }

        // 2. Рассчитываем параметры батча
        $delaySeconds = 20;
        $maxItemsPerHour = floor(3600 / $delaySeconds); // 180 предметов в час

        // 3. Берем батч предметов для обновления
        $batchItems = $marketHashNames->take($maxItemsPerHour);

        $this->info("Запланировано к обновлению: {$batchItems->count()} предметов");
        $this->info("Время выполнения: ~" . ($batchItems->count() * $delaySeconds / 60) . " минут");

        // 4. Запускаем job'ы с задержкой
        $delay = 0;
        foreach ($batchItems as $marketHashName) {
            FetchSteamPriceHistory::dispatch($marketHashName)
                ->delay(now()->addSeconds($delay * $delaySeconds))
                ->onQueue('default');
            
            $delay++;
        }

        $this->info("✅ Запланировано {$batchItems->count()} обновлений цен с интервалом {$delaySeconds} сек");
    }

    /**
     * Получить предметы которые нужно обновить
     */
    private function getItemsToUpdate()
    {
        // Получаем данные без ORDER BY (избегаем MySQL ошибку с DISTINCT + ORDER BY)
        $allItems = DB::table('listings')
            ->join('steam_market_items', 'listings.market_hash_name', '=', 'steam_market_items.market_hash_name')
            ->where('listings.status', 'active')
            ->where(function($query) {
                $query->whereNull('steam_market_items.last_price_update')
                      ->orWhere('steam_market_items.last_price_update', '<', now()->subHours(24));
            })
            ->select('listings.market_hash_name', 'steam_market_items.last_price_update')
            ->distinct()
            ->get();

        // Сортируем в PHP для сохранения приоритета обновлений
        return $allItems->sortBy('last_price_update')->pluck('market_hash_name');
    }
}