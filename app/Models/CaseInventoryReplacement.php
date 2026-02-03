<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseInventoryReplacement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_inventory_item_id',
        'listing_id',
        'trade_offer_id',
        'original_price',
        'replacement_price',
        'status',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'replacement_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function caseInventoryItem(): BelongsTo
    {
        return $this->belongsTo(CaseInventoryItem::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function tradeOffer(): BelongsTo
    {
        return $this->belongsTo(TradeOffer::class);
    }
}
