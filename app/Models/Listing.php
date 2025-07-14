<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    protected $fillable = [
        'item_id',
        'seller_id',
        'buyer_id',
        'steam_asset_id',
        'steam_owner_id',
        'market_hash_name',
        'price',
        'currency',
        'type',
        'wear_condition',
        'float_value',
        'inspect_url',
        'status',
        'wear_value',
        'pattern_index',
        'stickers',
        'name_tag',
        'is_stattrak',
        'is_souvenir',
        'listed_at',
        'sold_at',
        'expires_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'float_value' => 'decimal:8',
        'wear_value' => 'float',
        'stickers' => 'array',
        'is_stattrak' => 'boolean',
        'is_souvenir' => 'boolean',
        'listed_at' => 'datetime',
        'sold_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_SOLD = 'sold';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeSold($query)
    {
        return $query->where('status', self::STATUS_SOLD);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function markAsSold(Client $buyer): void
    {
        $this->update([
            'status' => self::STATUS_SOLD,
            'buyer_id' => $buyer->id,
            'sold_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function getWearNameAttribute(): string
    {
        if ($this->wear_value === null) {
            return 'Не указано';
        }

        if ($this->wear_value <= 0.07) {
            return 'Прямо с завода';
        } elseif ($this->wear_value <= 0.15) {
            return 'Немного поношенное';
        } elseif ($this->wear_value <= 0.38) {
            return 'После полевых испытаний';
        } elseif ($this->wear_value <= 0.45) {
            return 'Поношенное';
        } else {
            return 'Закалённое в боях';
        }
    }
}
