<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedWord extends Model
{
    protected $fillable = [
        'word',
    ];

    protected static $cachedWords = null;

    public static function getCachedWords(): array
    {
        if (self::$cachedWords === null) {
            self::$cachedWords = self::pluck('word')->toArray();
        }

        return self::$cachedWords;
    }

    public static function clearCache(): void
    {
        self::$cachedWords = null;
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