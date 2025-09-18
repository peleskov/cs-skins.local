<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class OrderNotificationService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Отправить уведомления о создании заказа
     */
    public function sendOrderCreatedNotifications(Order $order): void
    {
        Log::info('ORDER_CREATED_NOTIFICATIONS_START', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id
        ]);

        // Уведомление покупателю о создании заказа
        if ($order->buyer) {
            $this->notificationService->sendOrderStatusNotification(
                $order->buyer,
                $order,
                null, // old_status = null для нового заказа
                $order->status,
                'buyer'
            );
        }

        // Уведомление продавцу о новом заказе
        if ($order->seller) {
            $this->notificationService->sendOrderStatusNotification(
                $order->seller,
                $order,
                null, // old_status = null для нового заказа
                $order->status,
                'seller'
            );
        }
    }

    /**
     * Отправить уведомления о смене статуса заказа
     */
    public function sendStatusChangeNotifications(Order $order, string $oldStatus, string $newStatus): void
    {
        Log::info('ORDER_STATUS_CHANGE_NOTIFICATIONS_START', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id
        ]);

        // Уведомление покупателю
        if ($order->buyer) {
            $this->notificationService->sendOrderStatusNotification(
                $order->buyer,
                $order,
                $oldStatus,
                $newStatus,
                'buyer'
            );
        }

        // Уведомление продавцу
        if ($order->seller) {
            $this->notificationService->sendOrderStatusNotification(
                $order->seller,
                $order,
                $oldStatus,
                $newStatus,
                'seller'
            );
        }
    }
}