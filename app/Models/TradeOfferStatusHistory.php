<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeOfferStatusHistory extends Model
{
    protected $table = 'trade_offer_status_history';
    
    protected $fillable = [
        'trade_offer_id',
        'status',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime'
    ];

    public $timestamps = false;

    public function tradeOffer(): BelongsTo
    {
        return $this->belongsTo(TradeOffer::class);
    }
}
