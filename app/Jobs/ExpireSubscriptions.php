<?php

namespace App\Jobs;

use App\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionService $subscriptionService): void
    {
        $count = $subscriptionService->checkAndExpire();

        if ($count > 0) {
            Log::info("Деактивировано просроченных подписок: {$count}");
        }
    }
}
