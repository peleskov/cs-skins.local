<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Listing;
use App\Models\TradeOffer;
use App\Models\Transaction;
use App\Models\Client;
use App\Jobs\ReleaseExpiredOrder;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function created(Order $order): void
    {
        Log::info('OrderObserver::created вызван', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id
        ]);

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $this->handleOrderPayment($order);

            // Отправляем уведомления о создании оплаченного заказа
            app(OrderNotificationService::class)->sendOrderCreatedNotifications($order);
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
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            Log::info('Order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $order->seller_id
            ]);

            // Отправляем уведомления о смене статуса
            app(OrderNotificationService::class)->sendStatusChangeNotifications($order, $oldStatus, $newStatus);

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

        // Для вывода из кейса - особая логика
        if ($order->payment_method === 'case_withdraw') {
            // Возвращаем виртуальный предмет в доступные
            $caseItemId = $order->cart_snapshot[0]['case_inventory_item_id'] ?? null;
            if ($caseItemId) {
                \App\Models\CaseInventoryItem::where('id', $caseItemId)
                    ->update(['status' => \App\Models\CaseInventoryItem::STATUS_AVAILABLE]);

                Log::info('Виртуальный предмет возвращён в инвентарь при отмене заказа', [
                    'order_id' => $order->id,
                    'case_inventory_item_id' => $caseItemId,
                ]);
            }
            // НЕ создаём refund и НЕ возвращаем деньги - покупатель не платил
            return;
        }

        // Создаем возврат средств
        Transaction::create([
            'type' => Transaction::TYPE_REFUND,
            'amount' => $order->total_amount,
            'status' => Transaction::STATUS_COMPLETED,
            'client_id' => $order->buyer_id,
            'order_id' => $order->id,
            'description' => "Заказ #{$order->order_number} ({$order->system_remarks})"
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

        // Всегда ставим холд на выплату продавцу
        // По умолчанию — наш холд (из настроек), потом расширение пришлёт реальный от Steam
        $tradeOffer = $order->tradeOffer;
        if ($tradeOffer) {
            if (!$tradeOffer->delay_settlement || !$tradeOffer->settlement_date) {
                // Ставим дефолтный холд — расширение перезапишет реальным от Steam
                $tradeOffer->update([
                    'delay_settlement' => true,
                    'settlement_date' => now()->addDays($this->getHoldDays()),
                ]);

                Log::info('Установлен дефолтный холд на выплату', [
                    'order_id' => $order->id,
                    'hold_days' => $this->getHoldDays(),
                    'settlement_date' => $tradeOffer->settlement_date->toDateTimeString()
                ]);
            }

            if ($tradeOffer->settlement_date->isFuture()) {
                Log::info('Выплата отложена до окончания холда', [
                    'order_id' => $order->id,
                    'settlement_date' => $tradeOffer->settlement_date->toDateTimeString()
                ]);
                return;
            }
        }

        // Холд закончился — выплачиваем продавцу
        $this->createSellerTransactions($order);
    }

    /**
     * Создание транзакций для продавца (вызывается когда холда нет или он закончился)
     */
    public function createSellerTransactions(Order $order): void
    {
        // Проверяем что транзакции ещё не созданы
        $existingTransaction = Transaction::where('order_id', $order->id)
            ->where('type', Transaction::TYPE_SALE)
            ->first();

        if ($existingTransaction) {
            Log::info('Seller transaction already exists', [
                'order_id' => $order->id,
                'transaction_id' => $existingTransaction->id
            ]);
            return;
        }

        $fee = $this->calculateOrderFee($order);
        $sellerAmount = $order->total_amount - $fee;

        // Создаем транзакцию продажи
        Transaction::create([
            'client_id' => $order->seller_id,
            'order_id' => $order->id,
            'type' => Transaction::TYPE_SALE,
            'amount' => $sellerAmount,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => "Продажа по заказу #{$order->order_number}",
            'metadata' => [
                'original_amount' => $order->total_amount,
                'fee_amount' => $fee
            ]
        ]);

        // Начисляем деньги продавцу
        $order->seller->credit($sellerAmount);

        Log::info('Seller paid for order', [
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'amount' => $sellerAmount,
            'fee' => $fee
        ]);

        // Создаем транзакцию комиссии (информационную)
        if ($fee > 0) {
            Transaction::create([
                'client_id' => $order->seller_id,
                'order_id' => $order->id,
                'type' => Transaction::TYPE_FEE,
                'amount' => $fee,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Комиссия с продажи по заказу #{$order->order_number}",
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

        // P2P маркетплейс — проверяем PREMIUM продавца
        $seller = $order->seller;
        if ($seller && $seller->premiumFeatureEnabled('marketplace_fee')) {
            return (float) $this->getSetting('premium_marketplace_fee', 6);
        }

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