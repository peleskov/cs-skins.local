<?php

namespace App\Jobs;

use Exception;
use Throwable;
use App\Models\ClientInventoryItem;
use App\Models\Listing;
use App\Services\Steam\FloatValueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchFloatValueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120]; // Увеличиваем задержку при retry

    public function __construct(
        public ClientInventoryItem $inventoryItem
    ) {}

    public function handle(FloatValueService $floatService): void
    {
        // Проверяем, не получили ли уже float данные
        if ($this->inventoryItem->float_value !== null) {
            Log::info('Float data already exists, updating related listings only', [
                'item_id' => $this->inventoryItem->id,
                'asset_id' => $this->inventoryItem->steam_asset_id
            ]);
            
            // Обновляем связанные листинги существующими данными
            $this->updateRelatedListings([
                'float_value' => $this->inventoryItem->float_value,
                'float_min' => $this->inventoryItem->float_min,
                'float_max' => $this->inventoryItem->float_max,
                'paint_index' => $this->inventoryItem->paint_index,
                'def_index' => $this->inventoryItem->def_index,
                'csfloat_id' => $this->inventoryItem->csfloat_id,
            ]);
            return;
        }

        // Проверяем наличие inspect_url
        if (empty($this->inventoryItem->inspect_url)) {
            Log::warning('No inspect URL available for item', [
                'item_id' => $this->inventoryItem->id,
                'asset_id' => $this->inventoryItem->steam_asset_id
            ]);
            return;
        }

        Log::info('Fetching float data for item', [
            'item_id' => $this->inventoryItem->id,
            'asset_id' => $this->inventoryItem->steam_asset_id,
            'item_name' => $this->inventoryItem->item_name
        ]);

        $floatData = $floatService->getFloatData($this->inventoryItem->inspect_url);

        if ($floatData === null) {
            Log::warning('Failed to fetch float data', [
                'item_id' => $this->inventoryItem->id,
                'asset_id' => $this->inventoryItem->steam_asset_id,
                'attempt' => $this->attempts()
            ]);
            
            // Если достигли максимального количества попыток, помечаем как failed
            if ($this->attempts() >= $this->tries) {
                $this->inventoryItem->update(['float_fetched_at' => now()]);
            }
            
            throw new Exception('Failed to fetch float data from CSFloat API');
        }

        // Обновляем данные в БД
        $this->inventoryItem->update($floatData);

        // Обновляем связанные листинги
        $this->updateRelatedListings($floatData);

        Log::info('Float data fetched successfully', [
            'item_id' => $this->inventoryItem->id,
            'asset_id' => $this->inventoryItem->steam_asset_id,
            'float_value' => $floatData['float_value'],
            'csfloat_id' => $floatData['csfloat_id']
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('FetchFloatValueJob failed permanently', [
            'item_id' => $this->inventoryItem->id,
            'asset_id' => $this->inventoryItem->steam_asset_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Помечаем как обработанный с ошибкой
        $this->inventoryItem->update(['float_fetched_at' => now()]);
    }

    /**
     * Обновляет связанные листинги с полученными float данными
     */
    private function updateRelatedListings(array $floatData): void
    {
        // Находим все активные листинги для этого steam_asset_id без float данных
        $listings = Listing::where('steam_asset_id', $this->inventoryItem->steam_asset_id)
            ->whereIn('status', ['pending', 'active', 'reserved'])
            ->whereNull('float_value') // Обновляем только те, у которых еще нет данных
            ->get();

        if ($listings->isEmpty()) {
            Log::debug('No listings without float data found for asset', [
                'asset_id' => $this->inventoryItem->steam_asset_id
            ]);
            return;
        }

        $updatedCount = 0;
        foreach ($listings as $listing) {
            $listing->update([
                'float_value' => $floatData['float_value'],
                'float_min' => $floatData['float_min'],
                'float_max' => $floatData['float_max'],
                'paint_index' => $floatData['paint_index'],
                'def_index' => $floatData['def_index'],
                'csfloat_id' => $floatData['csfloat_id'],
            ]);

            $updatedCount++;
            Log::debug('Updated listing with float data', [
                'listing_id' => $listing->id,
                'asset_id' => $this->inventoryItem->steam_asset_id,
                'float_value' => $floatData['float_value']
            ]);
        }

        Log::info('Updated related listings with float data', [
            'asset_id' => $this->inventoryItem->steam_asset_id,
            'listings_updated' => $updatedCount
        ]);
    }
}