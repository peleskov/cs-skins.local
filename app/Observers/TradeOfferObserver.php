<?php

namespace App\Observers;

use App\Models\TradeOffer;
use App\Models\TradeOfferStatusHistory;
use App\Jobs\ProcessTradeOffer;
use Illuminate\Support\Facades\Log;

class TradeOfferObserver
{
    private const FINALIZING_STATUSES = [
        TradeOffer::STATUS_ACCEPTED => 'completed',
        TradeOffer::STATUS_CANCELED => 'cancelled',
        TradeOffer::STATUS_DECLINED => 'cancelled',
        TradeOffer::STATUS_EXPIRED => 'cancelled',
        TradeOffer::STATUS_INVALID_ITEMS => 'cancelled',
        TradeOffer::STATUS_CANCELED_BY_SECOND_FACTOR => 'cancelled',
    ];



    public function updated(TradeOffer $tradeOffer): void
    {
        Log::info('TradeOfferObserver::updated вызван', [
            'trade_offer_id' => $tradeOffer->id,
            'order_id' => $tradeOffer->order_id,
            'changed_attributes' => $tradeOffer->getChanges(),
            'original_attributes' => $tradeOffer->getOriginal()
        ]);
        
        if ($tradeOffer->skipBroadcast ?? false) {
            return;
        }

        if ($tradeOffer->wasChanged('status')) {
            $oldStatus = $tradeOffer->getOriginal('status');
            $newStatus = $tradeOffer->status;
            
            /*
            Log::info('Trade offer status changed', [
                'trade_offer_id' => $tradeOffer->id,
                'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'order_id' => $tradeOffer->order_id
            ]);
            */

            // Записываем изменение статуса
            /*
            Log::info('Recording status change history', [
                'trade_offer_id' => $tradeOffer->id,
                'status' => $newStatus
            ]);
            */
            
            $this->recordStatusHistory($tradeOffer, $newStatus);
            $this->processFinalizedStatus($tradeOffer, $newStatus);
        }
    }

    private function processFinalizedStatus(TradeOffer $tradeOffer, string $newStatus): void
    {
        if (!isset(self::FINALIZING_STATUSES[$newStatus])) {
            // Логируем неизвестные/необработанные статусы для мониторинга
            $knownNonFinal = [
                TradeOffer::STATUS_ACTIVE,
                TradeOffer::STATUS_CREATED_NEEDS_CONFIRMATION,
                TradeOffer::STATUS_IN_ESCROW,
                TradeOffer::STATUS_PENDING,
            ];
            if (!in_array($newStatus, $knownNonFinal)) {
                Log::warning('Неизвестный статус трейда', [
                    'trade_offer_id' => $tradeOffer->id,
                    'status' => $newStatus,
                    'order_id' => $tradeOffer->order_id,
                ]);
            }
            return;
        }

        $order = $tradeOffer->order;
        if (!$order) {
            Log::warning('TradeOffer without order', [
                'trade_offer_id' => $tradeOffer->id,
                'status' => $newStatus
            ]);
            return;
        }

        if ($order->status !== 'processing') {
            Log::info('Order already finalized, skipping', [
                'order_id' => $order->id,
                'order_status' => $order->status,
                'trade_offer_status' => $newStatus
            ]);
            return;
        }

        switch ($newStatus) {
            case TradeOffer::STATUS_ACCEPTED:
                Log::info('Processing accepted trade', [
                    'order_id' => $order->id,
                    'trade_offer_id' => $tradeOffer->id
                ]);
                $order->complete();
                break;
                
            case TradeOffer::STATUS_CANCELED:
            case TradeOffer::STATUS_DECLINED:
            case TradeOffer::STATUS_EXPIRED:
            case TradeOffer::STATUS_INVALID_ITEMS:
            case TradeOffer::STATUS_CANCELED_BY_SECOND_FACTOR:
                $reason = match($newStatus) {
                    TradeOffer::STATUS_CANCELED => 'Трейд отменен продавцом',
                    TradeOffer::STATUS_DECLINED => 'Трейд отклонен покупателем',
                    TradeOffer::STATUS_EXPIRED => 'Истек срок действия трейда',
                    TradeOffer::STATUS_INVALID_ITEMS => 'Ошибка с предметами в трейде',
                    TradeOffer::STATUS_CANCELED_BY_SECOND_FACTOR => 'Трейд отменен из-за двухфакторной аутентификации',
                    default => 'Трейд отменен'
                };
                Log::info('Processing cancelled trade', [
                    'order_id' => $order->id,
                    'trade_offer_id' => $tradeOffer->id,
                    'reason' => $reason
                ]);
                $order->cancel($reason);
                break;
        }
    }

    private function recordStatusHistory(TradeOffer $tradeOffer, string $status): void
    {
        try {
            // Используем микросекунды для уникальности timestamp
            $timestamp = now();
            
            // Проверяем если запись с таким же временем уже существует
            // Так как MySQL timestamp имеет точность до секунд, добавляем целую секунду
            while (TradeOfferStatusHistory::where('trade_offer_id', $tradeOffer->id)
                ->where('created_at', $timestamp->format('Y-m-d H:i:s'))
                ->exists()) {
                $timestamp = $timestamp->addSecond();
            }
            
            TradeOfferStatusHistory::create([
                'trade_offer_id' => $tradeOffer->id,
                'status' => $status,
                'created_at' => $timestamp
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record trade offer status history', [
                'trade_offer_id' => $tradeOffer->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

}