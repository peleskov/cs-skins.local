<?php

namespace App\Console\Commands;

use App\Events\OnlineUpdated;
use App\Services\OnlineCounterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BroadcastOnlineCount extends Command
{
    protected $signature = 'online:broadcast';

    protected $description = 'Считает онлайн и пушит его в канал Reverb (online)';

    public function handle(OnlineCounterService $service): int
    {
        Cache::forget('online:current');
        $count = $service->currentCount();

        broadcast(new OnlineUpdated($count));

        $this->info("Online: {$count}");

        return self::SUCCESS;
    }
}
