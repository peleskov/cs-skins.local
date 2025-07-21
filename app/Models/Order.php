<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_number',
        'buyer_id',
        'total_amount',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'paid_at',
        'cart_snapshot',
        'notes'
    ];

    protected $casts = [
        'cart_snapshot' => 'array',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    /**
     * Покупатель заказа
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    /**
     * Альтернативное имя для связи buyer (для совместимости)
     */
    public function client(): BelongsTo
    {
        return $this->buyer();
    }

    /**
     * Генерация номера заказа
     */
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -8));
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Проверка статуса оплаты
     */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Проверка завершения заказа
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Отметить как оплаченный
     */
    public function markAsPaid(string $transactionId = null, string $paymentMethod = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_STATUS_PAID,
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
        ]);
    }
}
