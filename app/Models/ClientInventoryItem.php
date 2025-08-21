<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'descriptions',
        'item_id',
        'cached_at',
        'type_id',
        'quality_id',
        'rarity_id',
        'exterior_id',
        'item_nameid',
        'item_nameid_fetched_at',
    ];

    protected $casts = [
        'tradable' => 'boolean',
        'marketable' => 'boolean',
        'amount' => 'integer',
        'float_value' => 'float',
        'pattern_index' => 'integer',
        'stickers' => 'array',
        'descriptions' => 'array',
        'cached_at' => 'datetime',
        'item_nameid_fetched_at' => 'datetime',
    ];

    protected $appends = ['structured_tags'];


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function steamMarketItem(): BelongsTo
    {
        return $this->belongsTo(SteamMarketItem::class, 'item_nameid', 'item_nameid');
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tags', 'item_id', 'tag_id')
            ->where('item_type', 'inventory')
            ->join('tag_categories', 'tags.category_id', '=', 'tag_categories.id')
            ->select('tags.*', 'tag_categories.code as category_code', 'tag_categories.steam_category as category_name')
            ->orderBy('tag_categories.sort_order')
            ->orderBy('tags.sort_order');
    }

    public function getStructuredTagsAttribute()
    {
        if ($this->relationLoaded('tags') && $this->tags) {
            return $this->tags->map(function ($tag) {
                $translatedValue = __('tags.values.' . $tag->normalized_value, [], 'ru');
                
                // Если перевод не найден (возвращается ключ), используем Steam название
                if ($translatedValue === 'tags.values.' . $tag->normalized_value) {
                    $translatedValue = $tag->steam_localized_name ?? $tag->normalized_value;
                }
                
                return [
                    'id' => $tag->id,
                    'category_name' => __('tags.categories.' . $tag->category_code, [], 'ru'),
                    'display_name' => $translatedValue,
                    'color' => $tag->color,
                ];
            });
        }
        
        return collect();
    }
}
