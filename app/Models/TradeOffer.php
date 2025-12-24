<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Steam\TradeService;
use App\Jobs\ProcessTradeOffer;
use Exception;

class TradeOffer extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'buyer_id',
        'buyer_trade_url',
        'asset_ids',
        'status',
        'steam_trade_offer_id',
        'delay_settlement',
        'settlement_date',
    ];

    protected $casts = [
        'asset_ids' => 'array',
        'delay_settlement' => 'boolean',
        'settlement_date' => 'datetime',
    ];

    const STATUS_INVALID = 'Invalid';
    const STATUS_ACTIVE = 'Active'; 
    const STATUS_ACCEPTED = 'Accepted';
    const STATUS_COUNTERED = 'Countered';
    const STATUS_EXPIRED = 'Expired';
    const STATUS_CANCELED = 'Canceled';
    const STATUS_DECLINED = 'Declined';
    const STATUS_INVALID_ITEMS = 'InvalidItems';
    const STATUS_CREATED_NEEDS_CONFIRMATION = 'CreatedNeedsConfirmation';
    const STATUS_CANCELED_BY_SECOND_FACTOR = 'CanceledBySecondFactor';
    const STATUS_IN_ESCROW = 'InEscrow';
    const STATUS_PENDING = 'Pending';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TradeOfferStatusHistory::class)->orderBy('created_at');
    }


    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }


    protected static function boot()
    {
        parent::boot();
    }


     /**
     * Создать трейд в БД и автоматически отправить в очередь для создания в Steam
     */
    public static function create(array $attributes = []): self
    {
        $tradeOffer = static::query()->create($attributes);
        
        ProcessTradeOffer::dispatch($tradeOffer);
        
        return $tradeOffer;
    }





}
