<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'total_amount',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'paid_at',
        'reserved_until',
        'cart_snapshot',
        'notes',
        'system_remarks'
    ];

    protected $casts = [
        'cart_snapshot' => 'array',
        'paid_at' => 'datetime',
        'reserved_until' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    protected $appends = ['trade_status_history'];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    public function client(): BelongsTo
    {
        return $this->buyer();
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'reserved_by_order_id');
    }

    public function tradeOffer()
    {
        return $this->hasOne(TradeOffer::class);
    }

    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -8));
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }




    public function cancel(string $reason = 'Заказ отменен'): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'system_remarks' => $reason
        ]);
    }

    public function complete(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function getTradeStatusHistoryAttribute(): array
    {
        if (!$this->tradeOffer) {
            return [];
        }
        
        return $this->tradeOffer->statusHistory()
            ->select('status', 'created_at')
            ->orderBy('created_at')
            ->get()
            ->map(function ($history) {
                return [
                    'status' => $this->mapToFinalStatus($history->status),
                    'created_at' => $history->created_at
                ];
            })
            ->toArray();
    }

    private function mapToFinalStatus(string $status): string
    {
        return match($status) {
            'Accepted' => 'completed',
            'Declined', 'Expired', 'Canceled' => 'cancelled',
            default => $status
        };
    }

}
