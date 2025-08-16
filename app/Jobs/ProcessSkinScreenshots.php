<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\SkinScreenshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSkinScreenshots implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 120; // 2 минуты на выполнение
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('screenshots'); // устанавливаем очередь через метод
    }

    /**
     * Execute the job.
     */
    public function handle(SkinScreenshotService $screenshotService): void
    {
        //Log::info('Starting screenshot processing batch');

        // Берем 5 листингов без скриншотов
        $listings = Listing::whereNull('screenshots')
            ->whereNotNull('market_hash_name')
            ->where('market_hash_name', '!=', '')
            ->limit(5)
            ->get();

        if ($listings->isEmpty()) {
            //Log::info('No listings found for screenshot generation');
            return;
        }

        //Log::info('Found listings for screenshot generation', ['count' => $listings->count()]);

        foreach ($listings as $listing) {
            try {
                $success = $screenshotService->processListing($listing);
                
                if ($success) {
                    /*
                    Log::info('Screenshot processed successfully', [
                        'listing_id' => $listing->id
                    ]);
                    */
                } else {
                    Log::warning('Failed to process screenshot', [
                        'listing_id' => $listing->id
                    ]);
                }
                
                // Задержка между запросами (15 секунд)
                sleep(15);
                
            } catch (\Exception $e) {
                Log::error('Failed to generate screenshot for listing', [
                    'listing_id' => $listing->id,
                    'inspect_url' => $listing->inspect_url,
                    'error' => $e->getMessage()
                ]);
            }
        }

        //Log::info('Finished screenshot processing batch');
    }

}
