<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamPriceHistory extends Model
{
    protected $table = 'steam_price_history';

    protected $fillable = [
        'steam_market_item_id',
        'date',
        'price',
        'volume',
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
        'volume' => 'integer',
    ];

    public function steamMarketItem(): BelongsTo
    {
        return $this->belongsTo(SteamMarketItem::class);
    }

    public function scopeForPeriod($query, $days)
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    public function scopeLatest($query, $limit = 365)
    {
        return $query->orderBy('date', 'desc')->limit($limit);
    }
}