<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RarityCoefficient extends Model
{
    protected $fillable = [
        'steam_name',
        'display_name_ru',
        'display_name_en',
        'coefficient',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'coefficient' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Получить все активные коэффициенты в виде массива [steam_name => coefficient]
     */
    public static function getCoefficientsArray(): array
    {
        return Cache::remember('rarity_coefficients', 3600, function () {
            return self::where('is_active', true)
                ->pluck('coefficient', 'steam_name')
                ->toArray();
        });
    }

    /**
     * Получить коэффициент по steam_name
     */
    public static function getCoefficientByName(string $steamName): float
    {
        $coefficients = self::getCoefficientsArray();
        return $coefficients[$steamName] ?? 0.50; // Default 50%
    }

    /**
     * Очистить кэш коэффициентов
     */
    public static function clearCache(): void
    {
        Cache::forget('rarity_coefficients');
    }

    /**
     * Автоматически очищаем кэш при сохранении
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Получить отображаемое название на текущем языке
     */
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        
        if ($locale === 'ru') {
            return $this->display_name_ru;
        }
        
        return $this->display_name_en ?? $this->display_name_ru;
    }
}