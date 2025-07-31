<?php

namespace App\Services;

use App\Events\ExtensionEvents;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;

class WebSocketHandler
{
    private \App\Services\Steam\SessionCache $sessionCache;

    public function __construct(\App\Services\Steam\SessionCache $sessionCache)
    {
        $this->sessionCache = $sessionCache;
    }

    public function handleClientMessage(string $type, array $data, string $channel): void
    {
        $sellerId = $this->extractSellerIdFromChannel($channel);
        
        if (!$sellerId) {
            Log::warning('Could not extract seller ID from channel', ['channel' => $channel]);
            return;
        }

        switch ($type) {
            case 'session_data':
                $this->handleSessionData($sellerId, $data);
                break;
            case 'trade_created':
                $this->handleTradeCreated($sellerId, $data);
                break;
            case 'trade_error':
                $this->handleTradeError($sellerId, $data);
                break;
            case 'heartbeat':
                $this->handleHeartbeat($sellerId, $data);
                break;
            default:
                Log::info('Unknown message type', ['type' => $type, 'seller_id' => $sellerId]);
        }
    }

    private function handleSessionData(int $sellerId, array $data): void
    {
        if (!isset($data['session'])) {
            Log::warning('Session data missing session field', ['seller_id' => $sellerId]);
            return;
        }

        $sessionData = $data['session'];
        
        // Логируем полученные данные сессии для отладки
        Log::info('Received session data', [
            'seller_id' => $sellerId,
            'has_sessionid' => isset($sessionData['sessionid']),
            'has_steamLoginSecure' => isset($sessionData['steamLoginSecure']),
            'has_steamid' => isset($sessionData['steamid']),
            'session_keys' => array_keys($sessionData)
        ]);
        
        if (!isset($sessionData['sessionid']) || !isset($sessionData['steamid'])) {
            Log::warning('Invalid session data structure', [
                'seller_id' => $sellerId,
                'has_sessionid' => isset($sessionData['sessionid']),
                'has_steamid' => isset($sessionData['steamid'])
            ]);
            return;
        }

        $client = Client::find($sellerId);
        if (!$client) {
            Log::warning('Client not found', ['seller_id' => $sellerId]);
            return;
        }

        if ($client->steam_id !== $sessionData['steamid']) {
            Log::warning('Steam ID mismatch', [
                'seller_id' => $sellerId,
                'expected' => $client->steam_id,
                'received' => $sessionData['steamid']
            ]);
            return;
        }

        $success = $this->sessionCache->set($sellerId, $sessionData);
        
        if ($success) {
            $this->sendToClient($sellerId, 'session_received', [
                'status' => 'success',
                'expires_in' => $this->sessionCache->getExpiresInSeconds($sellerId)
            ]);
            
            Log::info('Session data processed successfully', ['seller_id' => $sellerId]);
        } else {
            Log::error('Failed to cache session data', ['seller_id' => $sellerId]);
        }
    }

    private function sendToClient(int $sellerId, string $eventType, array $data = []): void
    {
        $channel = "seller-{$sellerId}";
        
        try {
            ExtensionEvents::dispatch($channel, $eventType, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send message to client', [
                'seller_id' => $sellerId,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
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
        $activeTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->whereIn('status', [\App\Models\TradeOffer::STATUS_PENDING, \App\Models\TradeOffer::STATUS_DISPATCHED, \App\Models\TradeOffer::STATUS_SENT])
            ->count();
        
        // Завершенные трейды за сегодня
        $completedTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_COMPLETED)
            ->count();
        
        // Отмененные трейды за сегодня
        $cancelledTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_CANCELLED)
            ->count();
        
        // Всего трейдов за сегодня
        $totalTradesToday = \App\Models\TradeOffer::where('seller_id', $sellerId)
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