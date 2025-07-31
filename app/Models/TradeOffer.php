<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TradeOffer extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'buyer_id',  
        'buyer_trade_url',
        'asset_ids',
        'status',
        'is_ready',
        'steam_trade_offer_id',
    ];

    protected $casts = [
        'asset_ids' => 'array',
        'is_ready' => 'boolean',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_SENT = 'sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Связь с заказом
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Связь с продавцом
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    /**
     * Связь с покупателем
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_id');
    }


    /**
     * Проверка статусов
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isReady(): bool
    {
        return $this->is_ready;
    }

    /**
     * Boot the model and register event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($tradeOffer) {
            // Добавляем TradeOffer в очередь для обработки
            \App\Jobs\ProcessTradeOffer::dispatch($tradeOffer);
        });

        static::updated(function ($tradeOffer) {
            // Не отправляем события если есть флаг пропуска
            if ($tradeOffer->skipBroadcast ?? false) {
                return;
            }
            
            // Отправляем события при изменении статуса через умную отправку
            if ($tradeOffer->wasChanged('status')) {
                $newStatus = $tradeOffer->status;
                
                switch ($newStatus) {
                    case self::STATUS_SENT:
                        \App\Events\ExtensionEvents::tradeOfferSent($tradeOffer);
                        break;
                    case self::STATUS_COMPLETED:
                        \App\Events\ExtensionEvents::tradeOfferCompleted($tradeOffer);
                        break;
                    case self::STATUS_CANCELLED:
                        \App\Events\ExtensionEvents::tradeOfferCancelled($tradeOffer);
                        break;
                }
            }
        });
    }

    /**
     * Методы для изменения статуса
     */
    public function markAsSent(string $steamTradeOfferId): void
    {
        DB::transaction(function () use ($steamTradeOfferId) {
            // Обновляем текущий TradeOffer
            $this->update([
                'status' => self::STATUS_SENT,
                'steam_trade_offer_id' => $steamTradeOfferId,
                'is_ready' => false,
            ]);

            // Активируем следующий TradeOffer этого продавца
            self::where('seller_id', $this->seller_id)
                ->where('status', self::STATUS_PENDING)
                ->where('is_ready', false)
                ->orderBy('created_at', 'asc')
                ->limit(1)
                ->update(['is_ready' => true]);
        });
    }

    public function markAsDispatched(): void
    {
        $this->update([
            'status' => self::STATUS_DISPATCHED,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'is_ready' => false,
        ]);
    }



    /**
     * Scope для фильтрации по статусу
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
