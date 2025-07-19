<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'steam_internal_name',
        'normalized_value',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'category_id');
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(ClientInventoryItem::class, 'item_tags', 'tag_id', 'item_id')
            ->where('item_type', 'inventory');
    }

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'item_tags', 'tag_id', 'item_id')
            ->where('item_type', 'listing');
    }
}