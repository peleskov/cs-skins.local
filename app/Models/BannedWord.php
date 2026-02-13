<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BannedWord extends Model
{
    protected $fillable = [
        'word',
    ];

    public static function getCachedWords(): array
    {
        return Cache::remember('banned_words', 3600, function () {
            return self::pluck('word')->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('banned_words');
    }

    protected static function booted()
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }
}