<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'steam_market_hash_name',
        'steam_classid',
        'steam_instanceid',
        'name_ru',
        'name_en',
        'type',
        'weapon',
        'rarity',
        'image_url',
        'min_steam_price',
        'steam_listings_count',
        'is_valid',
        'buyout_coefficient',
        'description_ru',
        'description_en',
        'tags',
    ];

    protected $casts = [
        'min_steam_price' => 'decimal:2',
        'buyout_coefficient' => 'decimal:2',
        'is_valid' => 'boolean',
        'steam_listings_count' => 'integer',
        'tags' => 'array',
    ];

    // Type constants
    const TYPE_KNIFE = 'knife';
    const TYPE_PISTOL = 'pistol';
    const TYPE_RIFLE = 'rifle';
    const TYPE_SMG = 'smg';
    const TYPE_SHOTGUN = 'shotgun';
    const TYPE_MACHINEGUN = 'machinegun';
    const TYPE_SNIPER = 'sniper';
    const TYPE_GLOVES = 'gloves';
    const TYPE_STICKER = 'sticker';
    const TYPE_GRAFFITI = 'graffiti';
    const TYPE_CASE = 'case';
    const TYPE_KEY = 'key';
    const TYPE_MUSIC_KIT = 'music_kit';
    const TYPE_AGENT = 'agent';
    const TYPE_PASS = 'pass';

    // Rarity constants (from TZ)
    const RARITY_CONSUMER = 'consumer';           // Ширпотреб - 50%
    const RARITY_INDUSTRIAL = 'industrial';       // Промышленное - 50%
    const RARITY_MIL_SPEC = 'mil_spec';          // Армейское - 40%
    const RARITY_RESTRICTED = 'restricted';       // Запрещённое - 35%
    const RARITY_CLASSIFIED = 'classified';       // Засекреченное - 30%
    const RARITY_COVERT = 'covert';              // Тайное - 20%
    const RARITY_CONTRABAND = 'contraband';       // Контрабанда - 20%

    // Bot buyout coefficients from TZ
    const BUYOUT_COEFFICIENTS = [
        self::RARITY_CONSUMER => 0.50,
        self::RARITY_INDUSTRIAL => 0.50,
        self::RARITY_MIL_SPEC => 0.40,
        self::RARITY_RESTRICTED => 0.35,
        self::RARITY_CLASSIFIED => 0.30,
        self::RARITY_COVERT => 0.20,
        self::RARITY_CONTRABAND => 0.20,
    ];

    /**
     * Get localized name based on app locale
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ru' ? $this->name_ru : $this->name_en;
    }

    /**
     * Get localized description based on app locale
     */
    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'ru' ? $this->description_ru : $this->description_en;
    }

    /**
     * Calculate bot buyout price
     */
    public function getBuyoutPriceAttribute(): ?float
    {
        if (!$this->is_valid || !$this->min_steam_price) {
            return null;
        }

        $coefficient = $this->buyout_coefficient ?? self::BUYOUT_COEFFICIENTS[$this->rarity] ?? 0.20;
        return round($this->min_steam_price * $coefficient, 2);
    }

    /**
     * Check if item is valid for bot purchase (>200 listings on Steam)
     */
    public function updateValidityStatus(): void
    {
        $this->is_valid = $this->steam_listings_count > 200;
        $this->save();
    }

    /**
     * Get rarity color class for UI
     */
    public function getRarityColorAttribute(): string
    {
        return match($this->rarity) {
            self::RARITY_CONSUMER => 'text-secondary',
            self::RARITY_INDUSTRIAL => 'text-primary',
            self::RARITY_MIL_SPEC => 'text-info',
            self::RARITY_RESTRICTED => 'text-warning',
            self::RARITY_CLASSIFIED => 'text-danger',
            self::RARITY_COVERT => 'text-covert',
            self::RARITY_CONTRABAND => 'text-contraband',
            default => 'text-muted'
        };
    }

    /**
     * Scope for searching items
     */
    public function scopeSearch($query, $search)
    {
        return $query->whereFullText(['name_ru', 'name_en'], $search)
                    ->orWhere('name_ru', 'LIKE', "%{$search}%")
                    ->orWhere('name_en', 'LIKE', "%{$search}%");
    }

    /**
     * Scope for filtering by type
     */
    public function scopeOfType($query, $types)
    {
        if (is_string($types)) {
            $types = [$types];
        }
        return $query->whereIn('type', $types);
    }

    /**
     * Scope for filtering by rarity
     */
    public function scopeOfRarity($query, $rarities)
    {
        if (is_string($rarities)) {
            $rarities = [$rarities];
        }
        return $query->whereIn('rarity', $rarities);
    }

    /**
     * Scope for valid items (can be sold to bot)
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope for price range
     */
    public function scopePriceBetween($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('min_steam_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('min_steam_price', '<=', $max);
        }
        return $query;
    }
}
