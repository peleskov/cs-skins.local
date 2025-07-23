<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    // Константы статусов товаров
    const STATUS_RESERVED = 'reserved';
    const STATUS_TRADE_SENT = 'trade_sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'listing_id', 
        'seller_id',
        'quantity',
        'status',
        'reserved_until',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'trade_offer_id',
        'item_name',
        'item_image_url',
        'price',
        'seller_name',
        'buyer_name'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reserved_until' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    /**
     * Boot the model and register event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($orderItem) {
            // Отправляем WebSocket события при изменении статуса
            if ($orderItem->wasChanged('status')) {
                $newStatus = $orderItem->status;
                
                switch ($newStatus) {
                    case self::STATUS_RESERVED:
                        broadcast(new \App\Events\TradeReserved($orderItem));
                        break;
                    case self::STATUS_TRADE_SENT:
                        broadcast(new \App\Events\TradeSent($orderItem));
                        break;
                    case self::STATUS_COMPLETED:
                        broadcast(new \App\Events\TradeCompleted($orderItem));
                        break;
                    case self::STATUS_CANCELLED:
                        broadcast(new \App\Events\TradeCancelled($orderItem));
                        break;
                }
            }
        });
    }

    /**
     * Связь с заказом
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Связь с листингом
     */
    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Связь с продавцом
     */
    public function seller()
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    /**
     * Отправить трейд
     */
    public function sendTrade(): void
    {
        $this->update([
            'status' => self::STATUS_TRADE_SENT,
        ]);
    }

    /**
     * Завершить
     */
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Отменить
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
