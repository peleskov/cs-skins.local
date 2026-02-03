<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_hash_name',
        'name',
        'weapon_type',
        'skin_name',
        'quality',
        'rarity',
        'rarity_color',
        'image_url',
        'steam_class_id',
        'price',
        'steam_price',
        'is_stattrak',
        'is_souvenir',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'steam_price' => 'decimal:2',
        'is_stattrak' => 'boolean',
        'is_souvenir' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Quality constants
    const QUALITY_FACTORY_NEW = 'Factory New';
    const QUALITY_MINIMAL_WEAR = 'Minimal Wear';
    const QUALITY_FIELD_TESTED = 'Field-Tested';
    const QUALITY_WELL_WORN = 'Well-Worn';
    const QUALITY_BATTLE_SCARRED = 'Battle-Scarred';

    // Rarity constants
    const RARITY_CONSUMER = 'Consumer Grade';
    const RARITY_INDUSTRIAL = 'Industrial Grade';
    const RARITY_MIL_SPEC = 'Mil-Spec Grade';
    const RARITY_RESTRICTED = 'Restricted';
    const RARITY_CLASSIFIED = 'Classified';
    const RARITY_COVERT = 'Covert';
    const RARITY_CONTRABAND = 'Contraband';

    // Rarity colors (CS2 colors)
    const RARITY_COLORS = [
        self::RARITY_CONSUMER => '#b0c3d9',
        self::RARITY_INDUSTRIAL => '#5e98d9',
        self::RARITY_MIL_SPEC => '#4b69ff',
        self::RARITY_RESTRICTED => '#8847ff',
        self::RARITY_CLASSIFIED => '#d32ce6',
        self::RARITY_COVERT => '#eb4b4b',
        self::RARITY_CONTRABAND => '#e4ae39',
    ];

    /**
     * Get all quality options
     */
    public static function getQualities(): array
    {
        return [
            self::QUALITY_FACTORY_NEW,
            self::QUALITY_MINIMAL_WEAR,
            self::QUALITY_FIELD_TESTED,
            self::QUALITY_WELL_WORN,
            self::QUALITY_BATTLE_SCARRED,
        ];
    }

    /**
     * Get all rarity options
     */
    public static function getRarities(): array
    {
        return [
            self::RARITY_CONSUMER,
            self::RARITY_INDUSTRIAL,
            self::RARITY_MIL_SPEC,
            self::RARITY_RESTRICTED,
            self::RARITY_CLASSIFIED,
            self::RARITY_COVERT,
            self::RARITY_CONTRABAND,
        ];
    }

    /**
     * Get rarity color
     */
    public function getRarityColorAttribute(): ?string
    {
        if ($this->attributes['rarity_color']) {
            return $this->attributes['rarity_color'];
        }
        return self::RARITY_COLORS[$this->rarity] ?? null;
    }

    /**
     * Scope for active items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for items by weapon type
     */
    public function scopeByWeaponType($query, string $weaponType)
    {
        return $query->where('weapon_type', $weaponType);
    }

    /**
     * Scope for items by rarity
     */
    public function scopeByRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    /**
     * Scope for items by quality
     */
    public function scopeByQuality($query, string $quality)
    {
        return $query->where('quality', $quality);
    }

    /**
     * Scope for price range
     */
    public function scopePriceRange($query, float $min, float $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Get short quality code (fn, mw, ft, ww, bs) for matching with Listing.wear_name
     */
    public function getShortQuality(): ?string
    {
        return match($this->quality) {
            'Factory New' => 'fn',
            'Minimal Wear' => 'mw',
            'Field-Tested' => 'ft',
            'Well-Worn' => 'ww',
            'Battle-Scarred' => 'bs',
            default => null,
        };
    }
}
