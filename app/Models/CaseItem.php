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
}
