<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CancelOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderCancelController extends Controller
{
    private CancelOrderService $cancelService;

    public function __construct(CancelOrderService $cancelService)
    {
        $this->cancelService = $cancelService;
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        
        // Проверка прав
        if ($user->id !== $order->buyer_id && $user->id !== $order->seller_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав на отмену этого заказа'
            ], 403);
        }
        
        // Проверка статуса
        if ($order->status === Order::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Завершенный заказ нельзя отменить'
            ], 400);
        }
        
        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ уже отменен'
            ], 400);
        }
        
        // Определяем причину отмены
        $reason = $user->id === $order->buyer_id 
            ? 'Отменено покупателем' 
            : 'Отменено продавцом';
        
        // Отменяем заказ
        $result = $this->cancelService->cancelOrder($order, $reason);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}