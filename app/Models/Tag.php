<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_code',
        'steam_internal_name', 
        'normalized_value',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Получить локализованное название категории
     */
    public function getCategoryNameAttribute(): string
    {
        $translation = trans("tags.categories.{$this->category_code}");
        return $translation !== "tags.categories.{$this->category_code}" ? $translation : ucfirst($this->category_code);
    }

    /**
     * Получить локализованное название тега
     */
    public function getLocalizedNameAttribute(): string
    {
        $translation = trans("tags.values.{$this->normalized_value}");
        return $translation !== "tags.values.{$this->normalized_value}" ? $translation : ucfirst($this->normalized_value);
    }

    /**
     * Получить market_hash_name связанные с этим тегом
     */
    public function marketItems()
    {
        return $this->belongsToMany(
            related: ClientInventoryItem::class, 
            table: 'market_item_tags',
            foreignPivotKey: 'tag_id',
            relatedPivotKey: 'market_hash_name',
            parentKey: 'id',
            relatedKey: 'market_hash_name'
        );
    }

    /**
     * Scope для фильтрации по категории
     */
    public function scopeByCategory($query, string $categoryCode)
    {
        return $query->where('category_code', $categoryCode);
    }

    /**
     * Scope для основных категорий (type, rarity, quality, exterior)
     */
    public function scopePrimary($query)
    {
        return $query->whereIn('category_code', ['type', 'rarity', 'quality', 'exterior']);
    }
}