<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Steam\TradeService;
use Illuminate\Support\Facades\Log;

class CancelOrderService
{
    private TradeService $tradeService;

    public function __construct(TradeService $tradeService)
    {
        $this->tradeService = $tradeService;
    }

    public function cancelOrder(Order $order, string $reason, int $maxAttempts = 2): array
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return ['success' => true, 'message' => 'Заказ уже отменен'];
        }

        $tradeOffer = $order->tradeOffer;
        
        if (!$tradeOffer || !$tradeOffer->steam_trade_offer_id) {
            // Трейда нет или не создан в Steam - отменяем заказ напрямую
            $order->cancel($reason);
            return ['success' => true, 'message' => 'Заказ отменен'];
        }

        // Трейд есть в Steam - отменяем только трейд, TradeOfferObserver отменит заказ
        // Предустанавливаем правильную причину отмены
        $order->update(['system_remarks' => $reason]);
        
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            try {
                $result = $this->tradeService->cancelTradeOffer($tradeOffer);
                
                if ($result['success']) {
                    // НЕ вызываем $order->cancel() - TradeOfferObserver сам обработает
                    return ['success' => true, 'message' => 'Трейд отменен, заказ будет отменен автоматически'];
                }
                
                Log::error('Не удалось отменить трейд в Steam', [
                    'order_id' => $order->id,
                    'trade_offer_id' => $tradeOffer->id,
                    'result' => $result
                ]);
                
                $order->update([
                    'status' => Order::STATUS_PROCESSING,
                    'system_remarks' => 'Не удается автоматически отменить трейд, требуется ручное вмешательство'
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Не удалось отменить трейд в Steam',
                    'requires_manual' => true
                ];
                
            } catch (\Exception $e) {
                $attempts++;
                
                Log::error('Ошибка при отмене трейда в Steam', [
                    'order_id' => $order->id,
                    'trade_offer_id' => $tradeOffer->id,
                    'error' => $e->getMessage(),
                    'attempt' => $attempts
                ]);
                
                if ($attempts >= $maxAttempts) {
                    $order->update([
                        'status' => Order::STATUS_PROCESSING,
                        'system_remarks' => 'Не удается автоматически отменить трейд, требуется ручное вмешательство'
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при отмене трейда',
                        'requires_manual' => true
                    ];
                }
                
                sleep(1);
            }
        }
        
        return [
            'success' => false,
            'message' => 'Не удалось отменить заказ'
        ];
    }
}