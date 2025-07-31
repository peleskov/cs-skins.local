<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Jobs\ReleaseExpiredOrder;

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

    /**
     * Покупатель заказа
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }

    /**
     * Продавец заказа
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
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
    public function listings()
    {
        return $this->hasMany(Listing::class, 'reserved_by_order_id');
    }

    /**
     * Торговые предложения заказа
     */
    public function tradeOffers()
    {
        return $this->hasMany(TradeOffer::class);
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
     * Оплатить заказ - выполняется после успешной оплаты
     */
    public function pay(string $transactionId = null, string $paymentMethod = null): void
    {
        // Обновляем статус заказа
        $this->update([
            'payment_status' => self::PAYMENT_STATUS_PAID,
            'status' => self::STATUS_PROCESSING,
            'paid_at' => now(),
            'payment_transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
        ]);

        $this->reserveListings();

        // Создаем TradeOffer после резервирования всех товаров
        $this->createTradeOffers();

        $this->createExpiredJob();
    }

    private function createExpiredJob(): void
    {
        if ($this->reserved_until) {
            $delayMinutes = now()->diffInMinutes($this->reserved_until);
            ReleaseExpiredOrder::dispatch($this->id)
                ->delay(now()->addMinutes($delayMinutes));
        }
    }

    private function reserveListings(): void
    {
        $finalReserveTime = null;
        
        foreach ($this->cart_snapshot as $item) {
            if (isset($item['seller_id'])) {
                $listing = Listing::find($item['listing_id']);
                if ($listing) {
                    $listing->reserveForOrder($this->id);
                }

                if ($finalReserveTime === null) {
                    $finalReserveTime = $this->getReserveTimeForSeller($item['seller_id']);
                }
            }
        }
        
        if ($finalReserveTime !== null) {
            $this->update(['reserved_until' => now()->addMinutes($finalReserveTime)]);
        }
    }

    public function releaseListings(): void
    {
        Listing::where('reserved_by_order_id', $this->id)->get()->each->release();
    }

    public function cancel(string $reason = 'Заказ отменен'): void
    {
        DB::transaction(function () use ($reason) {
            $this->tradeOffers()->update([
                'status' => TradeOffer::STATUS_CANCELLED,
                'is_ready' => false
            ]);
            
            $this->update([
                'status' => self::STATUS_CANCELLED,
                'system_remarks' => $reason
            ]);
            
            $this->releaseListings();
            
            $this->refundPayment($reason);
        });
    }

    private function refundPayment(string $reason): void
    {
        Transaction::create([
            'type' => Transaction::TYPE_REFUND,
            'amount' => $this->total_amount,
            'client_id' => $this->buyer_id,
            'description' => "Возврат средств за заказ #{$this->order_number} ({$reason})"
        ]);
    }

    public function complete(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Создание торгового предложения для заказа
     * Каждый заказ теперь относится к одному продавцу
     */
    private function createTradeOffers(): void
    {
        if (empty($this->cart_snapshot)) {
            return;
        }

        $sellerId = $this->seller_id;

        $activeTradesCount = TradeOffer::where('seller_id', $sellerId)
            ->whereIn('status', [TradeOffer::STATUS_PENDING, TradeOffer::STATUS_DISPATCHED])
            ->count();

        $assetIds = collect($this->cart_snapshot)->map(function ($item) {
            return $item['item']['steam_asset_id'] ?? null;
        })->filter()->values()->toArray();

        $isReady = $activeTradesCount === 0;

        TradeOffer::create([
            'order_id' => $this->id,
            'seller_id' => $sellerId,
            'buyer_id' => $this->buyer_id,
            'buyer_trade_url' => $this->buyer->steam_trade_url,
            'asset_ids' => $assetIds,
            'status' => TradeOffer::STATUS_PENDING,
            'is_ready' => $isReady,
        ]);
    }

    /**
     * Получение времени резерва для продавца по текущей очереди
     */
    private function getReserveTimeForSeller(int $sellerId): int
    {
        $activeTradesCount = TradeOffer::where('seller_id', $sellerId)
            ->where('status', TradeOffer::STATUS_PENDING)
            ->count();
            
        return $this->calculateDynamicReserveTime($activeTradesCount);
    }

    /**
     * Расчет динамического времени резерва на основе позиции в очереди
     */
    private function calculateDynamicReserveTime(int $queuePosition): int
    {
        $baseTime = (int) env('RESERVATION_TIME_MINUTES', 5); // уже содержит буфер
        $timePerTrade = (int) env('TIME_PER_TRADE_SECONDS', 30) / 60; // конвертируем в минуты
        $maxReserveTime = (int) env('MAX_RESERVATION_TIME_MINUTES', 60);

        $calculatedTime = $baseTime + ($queuePosition * $timePerTrade);

        return min($calculatedTime, $maxReserveTime);
    }
}
