<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'name',
        'symbol', 
        'code',
        'exchange_rate',
        'is_primary',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Boot метод для модели
     */
    protected static function boot()
    {
        parent::boot();
        
        // При сохранении основной валюты убираем флаг у других
        static::saving(function ($currency) {
            if ($currency->is_primary) {
                // Основная валюта всегда активна
                $currency->is_active = true;
                // Курс основной валюты всегда 1
                $currency->exchange_rate = 1;
                
                // Убираем флаг основной у других валют
                static::where('id', '!=', $currency->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
        
        // Очищаем кеш при изменении
        static::saved(function () {
            Cache::forget('currencies_active');
            Cache::forget('currency_primary');
        });
        
        static::deleted(function () {
            Cache::forget('currencies_active');
            Cache::forget('currency_primary');
        });
    }

    /**
     * Получить основную валюту
     */
    public static function primary()
    {
        return Cache::remember('currency_primary', 3600, function () {
            return static::where('is_primary', true)->first();
        });
    }

    /**
     * Получить активные валюты
     */
    public static function active()
    {
        return Cache::remember('currencies_active', 3600, function () {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Конвертировать сумму из одной валюты в другую
     */
    public static function convert($amount, $fromCurrencyCode, $toCurrencyCode)
    {
        if ($fromCurrencyCode === $toCurrencyCode) {
            return $amount;
        }

        $fromCurrency = static::where('code', $fromCurrencyCode)->first();
        $toCurrency = static::where('code', $toCurrencyCode)->first();

        if (!$fromCurrency || !$toCurrency) {
            return $amount;
        }

        // Конвертируем через основную валюту
        $primaryAmount = $amount / $fromCurrency->exchange_rate;
        return $primaryAmount * $toCurrency->exchange_rate;
    }

    /**
     * Форматировать сумму с символом валюты
     */
    public function formatAmount($amount)
    {
        return $this->symbol . ' ' . number_format($amount, 2, '.', ' ');
    }
}