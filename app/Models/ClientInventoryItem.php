<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'steam_asset_id',
        'steam_class_id',
        'steam_instance_id',
        'market_hash_name',
        'item_name',
        'type',
        'icon_url',
        'tradable',
        'marketable',
        'amount',
        'float_value',
        'pattern_index',
        'stickers',
        'inspect_url',
        'tags',
        'descriptions',
        'item_id',
        'cached_at',
    ];

    protected $casts = [
        'tradable' => 'boolean',
        'marketable' => 'boolean',
        'amount' => 'integer',
        'float_value' => 'float',
        'pattern_index' => 'integer',
        'stickers' => 'array',
        'tags' => 'array',
        'descriptions' => 'array',
        'cached_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeTradable($query)
    {
        return $query->where('tradable', true);
    }

    public function scopeMarketable($query)
    {
        return $query->where('marketable', true);
    }

    public function getFullIconUrlAttribute(): ?string
    {
        if (!$this->icon_url) {
            return null;
        }
        
        return 'https://community.steamstatic.com/economy/image/' . $this->icon_url;
    }

    public function hasWear(): bool
    {
        return $this->float_value !== null;
    }

    public function getWearConditionAttribute(): ?string
    {
        if (!$this->hasWear()) {
            return null;
        }

        $float = $this->float_value;

        if ($float >= 0.45) return 'Battle-Scarred';
        if ($float >= 0.38) return 'Well-Worn';
        if ($float >= 0.15) return 'Field-Tested';
        if ($float >= 0.07) return 'Minimal Wear';
        return 'Factory New';
    }

    public function hasStickers(): bool
    {
        return !empty($this->stickers);
    }

    public function getStickerCountAttribute(): int
    {
        return count($this->stickers ?? []);
    }

    public function isFromDatabase(): bool
    {
        return $this->item_id !== null;
    }
}
