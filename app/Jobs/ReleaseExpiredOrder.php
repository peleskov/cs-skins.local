<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Transaction;

class ReleaseExpiredOrder implements ShouldQueue
{
    use Queueable;

    public $tries = 2;              // Максимум 2 попытки
    public $backoff = [30];         // 30 секунд между попытками
    public $timeout = 30;           // 30 секунд timeout на попытку

    private int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::with(['buyer', 'tradeOffers'])->find($this->orderId);
        
        if (!$order) {
            Log::error("Order {$this->orderId} not found");
            return;
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return;
        }
        
        if (!$order->reserved_until || $order->reserved_until > now()) {
            return;
        }

        $tradeOffer = $order->tradeOffers()->first();
        
        if ($tradeOffer) {
            Log::info('Отправка команды на отмену Steam трейда по истечению резерва', [
                'order_id' => $order->id,
                'trade_offer_id' => $tradeOffer->id,
                'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id
            ]);
            
            if ($this->attempts() > 1) {
                $order->refresh();
                
                if ($order->status === Order::STATUS_CANCELLED) {
                    Log::info('Order уже отменен, завершаем job', [
                        'order_id' => $order->id,
                        'attempt' => $this->attempts()
                    ]);
                    return;
                }
            }
            
            if ($tradeOffer->steam_trade_offer_id) {
                // Есть Steam ID - отправляем команду в расширение и ждем ответа
                \App\Events\ExtensionEvents::cancelSteamTrade($tradeOffer);
                $this->release(30);
                return;
            } else {
                // Нет Steam ID - отменяем заказ сразу на сервере
                Log::info('TradeOffer без steam_trade_offer_id, отменяем заказ на сервере', [
                    'order_id' => $order->id,
                    'trade_offer_id' => $tradeOffer->id
                ]);
                
                // Уведомляем расширение об отмене
                \App\Events\ExtensionEvents::sendSmart(
                    '', 
                    $tradeOffer->seller_id, 
                    [],
                    "Заказ #{$order->order_number} отменен - трейд не был создан в Steam, время резерва истекло"
                );
                
                $order->cancel('Трейд не был создан в Steam, резерв истек');
                return;
            }
            
        } else {
            Log::warning('Order без TradeOffer при истечении резерва', [
                'order_id' => $order->id
            ]);
        }
    }

}