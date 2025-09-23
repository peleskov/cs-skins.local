<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    protected $fillable = [
        'seller_id',
        'buyer_id',
        'steam_asset_id',
        'steam_class_id',
        'steam_instance_id',
        'steam_owner_id',
        'market_hash_name',
        'inventory_item_name',
        'inventory_icon_url',
        'inventory_descriptions',
        'tradable',
        'marketable',
        'price',
        'currency',
        'type',
        'wear_condition',
        'float_value',
        'float_min',
        'float_max',
        'paint_index',
        'def_index',
        'csfloat_id',
        'inspect_url',
        'screenshots',
        'status',
        'reserved_by_order_id',
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
        'float_min' => 'float',
        'float_max' => 'float',
        'paint_index' => 'integer',
        'def_index' => 'integer',
        'csfloat_id' => 'integer',
        'wear_value' => 'float',
        'stickers' => 'array',
        'screenshots' => 'array',
        'inventory_descriptions' => 'array',
        'tradable' => 'boolean',
        'marketable' => 'boolean',
        'is_stattrak' => 'boolean',
        'is_souvenir' => 'boolean',
        'listed_at' => 'datetime',
        'sold_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    
    protected $appends = ['structured_tags', 'wear_name'];

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD = 'sold';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';


    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(ClientInventoryItem::class, 'steam_asset_id', 'steam_asset_id');
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

    public function sell(Client $buyer): void
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

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function reserveForOrder(int $orderId): bool
    {
        $updated = $this->where('id', $this->id)
            ->where('status', self::STATUS_ACTIVE)
            ->update([
                'status' => self::STATUS_RESERVED,
                'reserved_by_order_id' => $orderId,
            ]);
            
        return $updated > 0;
    }

    public function release(): bool
    {
        $updated = $this->where('id', $this->id)
            ->where('status', self::STATUS_RESERVED)
            ->update([
                'status' => self::STATUS_ACTIVE,
                'reserved_by_order_id' => null,
            ]);
            
        return $updated > 0;
    }

    public function expire(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    public function getWearNameAttribute(): string
    {
        // Приоритет: float_value -> wear_value -> "unknown"
        $floatValue = $this->float_value ?? $this->wear_value;

        if ($floatValue === null) {
            return 'unknown';
        }

        // Преобразуем в число для корректного сравнения
        $floatValue = (float) $floatValue;

        if ($floatValue <= 0.07) {
            return 'fn';
        } elseif ($floatValue <= 0.15) {
            return 'mw';
        } elseif ($floatValue <= 0.38) {
            return 'ft';
        } elseif ($floatValue <= 0.45) {
            return 'ww';
        } else {
            return 'bs';
        }
    }
    
    /**
     * Получить теги через market_hash_name
     */
    public function tags()
    {
        return Tag::join('market_item_tags', 'tags.id', '=', 'market_item_tags.tag_id')
            ->where('market_item_tags.market_hash_name', $this->market_hash_name)
            ->orderBy('tags.category_code')
            ->orderBy('tags.sort_order')
            ->get();
    }

    /**
     * Активный аукцион для листинга
     */
    public function activeAuction(): HasOne
    {
        return $this->hasOne(Auction::class)->where('status', Auction::STATUS_ACTIVE);
    }

    /**
     * Все аукционы для листинга
     */
    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    /**
     * Проверка, находится ли листинг на аукционе
     */
    public function isOnAuction(): bool
    {
        return $this->hasOne(Auction::class)
            ->whereIn('status', [Auction::STATUS_PENDING, Auction::STATUS_ACTIVE])
            ->exists();
    }

    public function getStructuredTagsAttribute()
    {
        $tags = $this->tags();
        
        if ($tags && $tags->isNotEmpty()) {
            return $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'category_code' => $tag->category_code,
                    'category_name' => $tag->category_name, // Использует геттер с переводами
                    'display_name' => $tag->localized_name, // Использует геттер с переводами
                    'normalized_value' => $tag->normalized_value,
                ];
            });
        }
        
        return collect();
    }
}
