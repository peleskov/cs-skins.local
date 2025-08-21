<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SteamMarketItem extends Model
{
    protected $fillable = [
        'market_hash_name',
        'item_nameid',
        'last_price_update',
    ];

    protected $casts = [
        'last_price_update' => 'datetime',
        'item_nameid' => 'integer',
    ];

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SteamPriceHistory::class);
    }

    public function getLatestPriceHistory($days = 365)
    {
        return $this->priceHistory()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'asc')
            ->get();
    }

    public function needsPriceUpdate(): bool
    {
        if (!$this->last_price_update) {
            return true;
        }

        return $this->last_price_update < now()->subHour();
    }
}