<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Listing;
use App\Models\TradeOffer;
use App\Models\Transaction;
use App\Models\Client;
use App\Jobs\ReleaseExpiredOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function created(Order $order): void
    {
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $this->handleOrderPayment($order);
        }
    }

    public function updated(Order $order): void
    {
        Log::info('OrderObserver::updated вызван', [
            'order_id' => $order->id,
            'changed_attributes' => $order->getChanges(),
            'original_attributes' => $order->getOriginal()
        ]);
        
        if ($order->wasChanged('status')) {
            Log::info('Order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $order->status,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $order->seller_id
            ]);

            // Бизнес-логика при смене статуса
            $this->handleStatusChange($order);
        }

        if ($order->wasChanged('payment_status')) {
            Log::info('Order payment status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_payment_status' => $order->getOriginal('payment_status'),
                'new_payment_status' => $order->payment_status
            ]);

            // Бизнес-логика при смене payment_status
            $this->handlePaymentStatusChange($order);
        }
    }

    private function handleStatusChange(Order $order): void
    {
        switch ($order->status) {
            case Order::STATUS_CANCELLED:
                $this->handleOrderCancellation($order);
                break;
            case Order::STATUS_COMPLETED:
                $this->handleOrderCompletion($order);
                break;
        }
    }

    private function handlePaymentStatusChange(Order $order): void
    {
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $this->handleOrderPayment($order);
        }
    }

    private function handleOrderPayment(Order $order): void
    {
        // Резервируем товары
        $this->reserveListings($order);
        
        // Создаем трейд-оффер
        $this->createTradeOffer($order);
        
        // Запускаем задачу освобождения просроченного заказа
        if ($order->reserved_until) {
            $delayMinutes = now()->diffInMinutes($order->reserved_until);
            ReleaseExpiredOrder::dispatch($order->id)
                ->delay(now()->addMinutes($delayMinutes));
        }
    }

    private function handleOrderCancellation(Order $order): void
    {
        // Отменяем связанный трейд
        if ($order->tradeOffer) {
            $order->tradeOffer->update([
                'status' => TradeOffer::STATUS_CANCELED
            ]);
        }
        
        // Освобождаем зарезервированные товары
        Listing::where('reserved_by_order_id', $order->id)->get()->each->release();
        
        // Создаем возврат средств
        Transaction::create([
            'type' => Transaction::TYPE_REFUND,
            'amount' => $order->total_amount,
            'status' => Transaction::STATUS_COMPLETED,
            'client_id' => $order->buyer_id,
            'order_id' => $order->id,
            'description' => "Возврат средств за заказ #{$order->order_number} ({$order->system_remarks})"
        ]);
        
        // Возвращаем средства на баланс покупателя
        if ($order->buyer_id) {
            $buyer = Client::find($order->buyer_id);
            if ($buyer) {
                Log::info('Возвращаем средства при отмене заказа через Observer', [
                    'order_id' => $order->id,
                    'buyer_id' => $order->buyer_id,
                    'amount' => $order->total_amount,
                    'old_balance' => $buyer->balance
                ]);
                $buyer->credit($order->total_amount);
                Log::info('Средства возвращены через Observer', [
                    'new_balance' => $buyer->fresh()->balance
                ]);
            }
        }
    }

    private function reserveListings(Order $order): void
    {
        $finalReserveTime = null;
        
        foreach ($order->cart_snapshot as $item) {
            if (isset($item['seller_id'])) {
                $listing = Listing::find($item['listing_id']);
                if ($listing) {
                    $listing->reserveForOrder($order->id);
                }

                if ($finalReserveTime === null) {
                    $finalReserveTime = $this->getReserveTimeForSeller($item['seller_id']);
                }
            }
        }
        
        if ($finalReserveTime !== null) {
            $order->reserved_until = now()->addMinutes($finalReserveTime);
            $order->saveQuietly();
        }
    }

    private function createTradeOffer(Order $order): void
    {
        if (empty($order->cart_snapshot)) {
            return;
        }

        $assetIds = collect($order->cart_snapshot)->map(function ($item) {
            return $item['item']['steam_asset_id'] ?? null;
        })->filter()->values()->toArray();

        TradeOffer::create([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'buyer_id' => $order->buyer_id,
            'buyer_trade_url' => $order->buyer->steam_trade_url,
            'asset_ids' => $assetIds,
            'status' => TradeOffer::STATUS_PENDING,
        ]);
    }

    private function getReserveTimeForSeller(int $sellerId): int
    {
        $activeTradesCount = TradeOffer::where('seller_id', $sellerId)
            ->where('status', TradeOffer::STATUS_PENDING)
            ->count();
            
        $baseTime = (int) env('RESERVATION_TIME_MINUTES', 5);
        $timePerTrade = (int) env('TIME_PER_TRADE_SECONDS', 30) / 60;
        $maxReserveTime = (int) env('MAX_RESERVATION_TIME_MINUTES', 60);

        $calculatedTime = $baseTime + ($activeTradesCount * $timePerTrade);

        return min($calculatedTime, $maxReserveTime);
    }

    private function handleOrderCompletion(Order $order): void
    {
        // Не создаем транзакции для призов из кейсов
        if ($order->payment_method === 'case_prize') {
            return;
        }

        $fee = $this->calculateOrderFee($order);
        $holdUntil = now()->addDays($this->getHoldDays());

        // Создаем транзакцию продажи (с холдом)
        Transaction::create([
            'client_id' => $order->seller_id,
            'order_id' => $order->id,
            'type' => Transaction::TYPE_SALE,
            'amount' => $order->total_amount - $fee,
            'status' => Transaction::STATUS_ON_HOLD,
            'description' => "Продажа по заказу #{$order->order_number}",
            'hold_until' => $holdUntil,
            'metadata' => [
                'original_amount' => $order->total_amount,
                'fee_amount' => $fee
            ]
        ]);

        // Создаем транзакцию комиссии (с холдом)
        if ($fee > 0) {
            Transaction::create([
                'client_id' => $order->seller_id,
                'order_id' => $order->id,
                'type' => Transaction::TYPE_FEE,
                'amount' => $fee,
                'status' => Transaction::STATUS_ON_HOLD,
                'description' => "Комиссия с продажи по заказу #{$order->order_number}",
                'hold_until' => $holdUntil,
                'metadata' => [
                    'fee_type' => $this->getOrderType($order),
                    'fee_percent' => $this->getFeePercent($order)
                ]
            ]);
        }
    }

    private function calculateOrderFee(Order $order): float
    {
        $feePercent = $this->getFeePercent($order);
        return $order->total_amount * ($feePercent / 100);
    }

    private function getFeePercent(Order $order): float
    {
        // Быстрая продажа боту
        if ($order->buyer && $order->buyer->is_bot) {
            return (float) $this->getSetting('bot_purchase_fee_percent', 0);
        }

        // Аукцион
        if ($order->auction()->exists()) {
            return (float) $this->getSetting('auction_fee_percent', 5);
        }

        // P2P маркетплейс (по умолчанию)
        return (float) $this->getSetting('marketplace_fee_percent', 5);
    }

    private function getOrderType(Order $order): string
    {
        if ($order->buyer && $order->buyer->is_bot) {
            return 'bot_purchase';
        }

        if ($order->auction()->exists()) {
            return 'auction';
        }

        return 'marketplace';
    }

    private function getHoldDays(): int
    {
        return (int) $this->getSetting('transaction_hold_days', 7);
    }

    private function getSetting(string $key, $default = null)
    {
        return \App\Models\SiteSetting::get($key, $default);
    }
}