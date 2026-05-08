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

    /**
     * Множитель для суточного профиля: пик ~20:00, минимум ~08:00.
     * Возвращает 1.0 если профиль выключен.
     */
    public function dailyFactor(): float
    {
        if (! SiteSetting::get('online_daily_profile', false)) {
            return 1.0;
        }

        $amplitude = (float) SiteSetting::get('online_daily_amplitude', 40) / 100;
        $amplitude = max(0.0, min(0.9, $amplitude));

        $hour = (int) now()->format('G') + (int) now()->format('i') / 60;
        // cos с пиком в 20:00 (peak hour 20, через 24ч цикл)
        $factor = 1.0 + $amplitude * cos(($hour - 20) / 24 * 2 * M_PI);

        return max(0.1, $factor);
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
            $fake = (int) round($fake * $this->dailyFactor());
            $target = max(0, $real + $fake);

            if ($mode === self::MODE_REAL || $fluct <= 0) {
                return $target;
            }

            // Случайное блуждание: дельта на тик ~3-7% от амплитуды,
            // с мягким притяжением к target, не выходя за ±fluct
            $prev = (int) Cache::get('online:last_value', $target);
            $offset = $prev - $target;

            $stepBase = max(1, (int) ceil($fluct * 0.05));
            $step = random_int($stepBase, max($stepBase + 1, (int) ceil($fluct * 0.12)));
            $direction = random_int(0, 99) < 50 ? -1 : 1;

            // Чем дальше от target, тем выше шанс пойти обратно
            if ($fluct > 0) {
                $bias = max(-1.0, min(1.0, $offset / $fluct));
                if (random_int(0, 99) / 100 < abs($bias) * 0.7) {
                    $direction = $bias > 0 ? -1 : 1;
                }
            }

            $next = $prev + $direction * $step;
            $next = max($target - $fluct, min($target + $fluct, $next));
            $next = max(0, $next);

            Cache::put('online:last_value', $next, 600);

            return $next;
        });
    }
}
