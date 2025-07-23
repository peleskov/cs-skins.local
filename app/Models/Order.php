<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Jobs\ReleaseExpiredOrderItem;

class Order extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PARTIALLY_COMPLETED = 'partially_completed';
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
     * Товары в заказе
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
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
     * Оплатить заказ
     */
    public function pay(string $transactionId = null, string $paymentMethod = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_STATUS_PAID,
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
        ]);

        // Создаем записи в order_items и резервируем листинги
        foreach ($this->cart_snapshot as $item) {
            if (isset($item['seller_id'])) {
                // Получаем листинг для резервирования
                $listing = Listing::find($item['listing_id']);
                if ($listing) {
                    // Резервируем листинг на 5 минут (по ТЗ)
                    $listing->reserve();
                }

                // Создаем запись в order_items с полными данными
                $reservationMinutes = (int) env('RESERVATION_TIME_MINUTES', 5);
                $orderItem = OrderItem::create([
                    'order_id' => $this->id,
                    'listing_id' => $item['listing_id'],
                    'seller_id' => $item['seller_id'],
                    'quantity' => 1,
                    'status' => OrderItem::STATUS_RESERVED,
                    'reserved_until' => now()->addMinutes($reservationMinutes),
                    'item_name' => $item['item']['name'] ?? 'Unknown Item',
                    'item_image_url' => $item['item']['image_url'] ?? '',
                    'price' => $item['price'],
                    'seller_name' => $item['seller']['name'] ?? 'Unknown Seller',
                    'buyer_name' => $this->buyer->name
                ]);

                // Запускаем отложенный job для автоматической отмены резерва
                ReleaseExpiredOrderItem::dispatch($orderItem->id)
                    ->delay(now()->addMinutes($reservationMinutes));
            }
        }
    }
}
