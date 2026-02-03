<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOpen extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'case_id',
        'case_inventory_item_id',
        'price_paid',
        'balance_used',
        'bonus_balance_used',
        'is_free',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'balance_used' => 'decimal:2',
        'bonus_balance_used' => 'decimal:2',
        'is_free' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(CaseInventoryItem::class, 'case_inventory_item_id');
    }
}
