<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\TradeOffer;
use App\Models\Transaction;
use App\Observers\OrderObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseSettledOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('ReleaseSettledOrders: Starting job');

        // Ищем завершённые заказы с холдом, у которых settlement_date уже прошла
        $ordersToRelease = Order::where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '<=', now());
            })
            ->whereDoesntHave('transactions', function ($query) {
                $query->where('type', Transaction::TYPE_SALE);
            })
            ->get();

        Log::info('ReleaseSettledOrders: Found orders to release', [
            'count' => $ordersToRelease->count()
        ]);

        $observer = app(OrderObserver::class);

        foreach ($ordersToRelease as $order) {
            try {
                Log::info('ReleaseSettledOrders: Processing order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'seller_id' => $order->seller_id,
                    'total_amount' => $order->total_amount,
                    'settlement_date' => $order->tradeOffer->settlement_date->toDateTimeString()
                ]);

                // Создаём транзакции продавцу
                $observer->createSellerTransactions($order);

                Log::info('ReleaseSettledOrders: Order released successfully', [
                    'order_id' => $order->id
                ]);
            } catch (\Exception $e) {
                Log::error('ReleaseSettledOrders: Failed to release order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('ReleaseSettledOrders: Job completed');
    }
}
