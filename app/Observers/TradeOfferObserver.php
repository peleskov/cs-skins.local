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
        if ($tradeOffer->skipBroadcast ?? false) {
            return;
        }

        if ($tradeOffer->wasChanged('status')) {
            $oldStatus = $tradeOffer->getOriginal('status');
            $newStatus = $tradeOffer->status;
            
            Log::info('Trade offer status changed', [
                'trade_offer_id' => $tradeOffer->id,
                'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'order_id' => $tradeOffer->order_id
            ]);

            // Если получили steam_trade_offer_id (трейд успешно создан), добавляем запись о создании
            if ($tradeOffer->wasChanged('steam_trade_offer_id') && $tradeOffer->steam_trade_offer_id) {
                Log::info('Recording trade creation history', [
                    'trade_offer_id' => $tradeOffer->id,
                    'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id
                ]);
                $this->recordStatusHistory($tradeOffer, 'Трейд создан в Steam');
            }

            // Записываем изменение статуса
            Log::info('Recording status change history', [
                'trade_offer_id' => $tradeOffer->id,
                'status' => $newStatus
            ]);
            $this->recordStatusHistory($tradeOffer, $newStatus);
            $this->processFinalizedStatus($tradeOffer, $newStatus);
        }
    }

    private function processFinalizedStatus(TradeOffer $tradeOffer, string $newStatus): void
    {
        if (!isset(self::FINALIZING_STATUSES[$newStatus])) {
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
            while (TradeOfferStatusHistory::where('trade_offer_id', $tradeOffer->id)
                ->where('created_at', $timestamp)
                ->exists()) {
                $timestamp = $timestamp->addMicrosecond();
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