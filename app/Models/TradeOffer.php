<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Steam\TradeService;
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
    ];

    protected $casts = [
        'asset_ids' => 'array',
    ];

    // Steam статусы (из steam-tradeoffer-manager ETradeOfferState)
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
    
    // Дополнительный локальный статус
    const STATUS_PENDING = 'Pending'; // Ожидает создания в Steam

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

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_CREATED_NEEDS_CONFIRMATION,
            self::STATUS_IN_ESCROW
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_CANCELED,
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
            self::STATUS_INVALID_ITEMS,
            self::STATUS_CANCELED_BY_SECOND_FACTOR
        ]);
    }

    public function isCountered(): bool
    {
        return $this->status === self::STATUS_COUNTERED;
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
            
            // Логируем изменения статуса для отладки
            if ($tradeOffer->wasChanged('status')) {
                $oldStatus = $tradeOffer->getOriginal('status');
                $newStatus = $tradeOffer->status;
                
                Log::info('Trade offer status changed', [
                    'trade_offer_id' => $tradeOffer->id,
                    'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            }
        });
    }

    /**
     * Установить статус от Steam
     */
    public function setStatus(string $steamStatus): void
    {
        $this->update(['status' => $steamStatus]);
    }

    /**
     * Steam API методы
     */

    /**
     * Создать трейд в БД и автоматически отправить в очередь для создания в Steam
     */
    public static function create(array $attributes = []): self
    {
        // Создаем запись в БД через родительский метод
        $tradeOffer = static::query()->create($attributes);
        
        // ProcessTradeOfferJob уже автоматически диспатчится в boot() методе при created event
        
        return $tradeOffer;
    }

    /**
     * Отменить трейд в Steam и обновить локальный статус
     */
    public function cancel(): bool
    {
        if (!$this->steam_trade_offer_id) {
            throw new Exception("Trade offer not created in Steam yet");
        }

        $steamTradeService = app(TradeService::class);
        $result = $steamTradeService->cancelTradeOffer($this);

        if ($result['success']) {
            $this->setStatus(self::STATUS_CANCELED);
            return true;
        }

        return false;
    }

    /**
     * Получить актуальный статус из Steam и обновить локальный
     */
    public function status(): string
    {
        if (!$this->steam_trade_offer_id) {
            throw new Exception("Trade offer not created in Steam yet");
        }

        $steamTradeService = app(TradeService::class);
        $steamStatus = $steamTradeService->checkTradeStatus($this->steam_trade_offer_id, $this->seller_id);

        // Обновляем локальный статус если изменился
        if ($steamStatus !== $this->status) {
            $this->setStatus($steamStatus);
        }

        return $steamStatus;
    }



    /**
     * Scope для фильтрации по статусу
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
