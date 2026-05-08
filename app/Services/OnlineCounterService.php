<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class OnlineCounterService
{
    public const KEY = 'online:visitors';

    public const MODE_REAL = 'real';

    public const MODE_REAL_WITH_FAKE = 'real_with_fake';

    public const MODE_FAKE = 'fake';

    public function track(string $visitorKey): void
    {
        $now = time();
        $window = (int) SiteSetting::get('online_window_seconds', 300);

        Redis::zadd(self::KEY, $now, $visitorKey);
        Redis::zremrangebyscore(self::KEY, '-inf', $now - $window);
        Redis::expire(self::KEY, $window * 2);
    }

    public function realCount(): int
    {
        $now = time();
        $window = (int) SiteSetting::get('online_window_seconds', 300);
        Redis::zremrangebyscore(self::KEY, '-inf', $now - $window);

        return (int) Redis::zcard(self::KEY);
    }

    public function currentCount(): int
    {
        return Cache::remember('online:current', 8, function () {
            $mode = SiteSetting::get('online_mode', self::MODE_REAL);
            $base = (int) SiteSetting::get('online_fake_base', 0);
            $fluct = (int) SiteSetting::get('online_fluctuation', 0);

            $real = $mode === self::MODE_FAKE ? 0 : $this->realCount();
            $fake = $mode === self::MODE_REAL ? 0 : $base;
            $jitter = $fluct > 0 ? random_int(-$fluct, $fluct) : 0;

            return max(0, $real + $fake + $jitter);
        });
    }
}
