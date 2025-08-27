<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\SkinScreenshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            $attemptKey = "screenshot_attempts_{$listing->id}";
            $attempts = Cache::get($attemptKey, 0);
            
            // Если уже было 3 попытки, помечаем как неудачное и пропускаем
            if ($attempts >= 3) {
                $listing->screenshots = 0;
                $listing->save();
                Cache::forget($attemptKey);
                continue;
            }
            
            try {
                // Увеличиваем счетчик попыток
                Cache::put($attemptKey, $attempts + 1, 3600); // храним час
                
                $success = $screenshotService->processListing($listing);
                
                if ($success) {
                    // Успешно - удаляем счетчик
                    Cache::forget($attemptKey);
                } else {
                    Log::warning('Failed to process screenshot', [
                        'listing_id' => $listing->id,
                        'attempt' => $attempts + 1
                    ]);
                    
                    // Если это была 3-я попытка, помечаем как неудачное
                    if ($attempts + 1 >= 3) {
                        $listing->screenshots = 0;
                        $listing->save();
                        Cache::forget($attemptKey);
                    }
                }
                
                // Задержка между запросами (15 секунд)
                sleep(15);
                
            } catch (\Exception $e) {
                Log::error('Failed to generate screenshot for listing', [
                    'listing_id' => $listing->id,
                    'inspect_url' => $listing->inspect_url,
                    'attempt' => $attempts + 1,
                    'error' => $e->getMessage()
                ]);
                
                // Если это была 3-я попытка, помечаем как неудачное
                if ($attempts + 1 >= 3) {
                    $listing->screenshots = 0;
                    $listing->save();
                    Cache::forget($attemptKey);
                }
            }
        }

        //Log::info('Finished screenshot processing batch');
    }

}
