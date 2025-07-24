<?php

namespace App\Services;

use App\Events\ExtensionEvents;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;

class WebSocketHandler
{
    /**
     * Обработка входящих WebSocket сообщений от расширения
     */
    public function handleClientMessage($event): void
    {
        // TODO: Реализовать обработку входящих сообщений когда разберемся со структурой событий Reverb
        Log::info('WebSocketHandler::handleClientMessage called', [
            'event_type' => get_class($event)
        ]);
    }


    /**
     * Обработка уведомления о создании трейда
     */
    private function handleTradeCreated(int $sellerId, array $data): void
    {
        Log::info('Trade created notification', [
            'seller_id' => $sellerId,
            'trade_data' => $data
        ]);
        
        // Здесь можно обновить статус заказа в БД если нужно
        // OrderItem::where('id', $data['order_id'])->update(['status' => 'trade_sent']);
        
        // Статистика теперь обновляется автоматически через WebSocket
    }

    /**
     * Обработка ошибки создания трейда
     */
    private function handleTradeError(int $sellerId, array $data): void
    {
        Log::error('Trade creation error', [
            'seller_id' => $sellerId,
            'error_data' => $data
        ]);
        
        // Здесь можно обновить статус заказа на ошибку
        // OrderItem::where('id', $data['order_id'])->update(['status' => 'error']);
    }

    /**
     * Обработка heartbeat от расширения
     */
    private function handleHeartbeat(int $sellerId, array $data): void
    {
        // Обновляем время последней активности продавца
        Client::where('id', $sellerId)->update([
            'last_extension_activity' => now()
        ]);
    }

    /**
     * Извлечение ID продавца из названия канала
     */
    private function extractSellerIdFromChannel(string $channel): ?int
    {
        if (preg_match('/seller-(\d+)/', $channel, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }

    /**
     * Получение статистики продавца
     */
    private function getSellerStats(int $sellerId): array
    {
        $today = today();
        
        // Активные трейды за сегодня
        $activeTrades = \App\Models\OrderItem::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\OrderItem::STATUS_TRADE_SENT)
            ->count();
        
        // Завершенные трейды за сегодня
        $completedTrades = \App\Models\OrderItem::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\OrderItem::STATUS_COMPLETED)
            ->count();
        
        // Отмененные трейды за сегодня
        $cancelledTrades = \App\Models\OrderItem::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\OrderItem::STATUS_CANCELLED)
            ->count();
        
        // Всего трейдов за сегодня
        $totalTradesToday = \App\Models\OrderItem::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->count();

        return [
            'statistics' => [
                'active' => $activeTrades,
                'completed' => $completedTrades,
                'cancelled' => $cancelledTrades,
                'total' => $totalTradesToday
            ],
            'updated_at' => now()->toISOString()
        ];
    }
}