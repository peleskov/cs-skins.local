<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseItem extends Model
{
    protected $fillable = [
        'case_id',
        'tier_id',
        'inventory_item_id',
        'virtual_item_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CaseTier::class, 'tier_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(ClientInventoryItem::class, 'inventory_item_id');
    }

    public function virtualItem(): BelongsTo
    {
        return $this->belongsTo(VirtualItem::class, 'virtual_item_id');
    }

    /**
     * Получить цену предмета (из case_item или из virtual_item)
     */
    public function getItemPrice(): ?float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        if ($this->virtualItem) {
            return (float) ($this->virtualItem->steam_price ?? $this->virtualItem->price);
        }

        return null;
    }

    /**
     * Получить название предмета
     */
    public function getItemName(): ?string
    {
        if ($this->virtualItem) {
            return $this->virtualItem->name;
        }

        if ($this->inventoryItem) {
            return $this->inventoryItem->name ?? $this->inventoryItem->market_hash_name;
        }

        return null;
    }

    /**
     * Получить изображение предмета
     */
    public function getItemImage(): ?string
    {
        if ($this->virtualItem) {
            return $this->virtualItem->image_url;
        }

        if ($this->inventoryItem) {
            return $this->inventoryItem->icon_url;
        }

        return null;
    }
}
