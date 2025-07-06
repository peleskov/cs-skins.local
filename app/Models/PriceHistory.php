<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    protected $fillable = [
        'item_id',
        'price_min',
        'price_max',
        'price_avg',
        'volume',
        'listings_count',
        'period',
        'recorded_at',
    ];

    protected $casts = [
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'price_avg' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    const PERIOD_HOUR = 'hour';
    const PERIOD_DAY = 'day';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    public static function recordPriceData(int $itemId, array $data, string $period = self::PERIOD_DAY): void
    {
        static::updateOrCreate(
            [
                'item_id' => $itemId,
                'period' => $period,
                'recorded_at' => now()->startOfDay(),
            ],
            [
                'price_min' => $data['min'],
                'price_max' => $data['max'],
                'price_avg' => $data['avg'],
                'volume' => $data['volume'] ?? 0,
                'listings_count' => $data['listings_count'] ?? 0,
            ]
        );
    }
}
