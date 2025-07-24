<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtensionEvents implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sellerId;
    public string $messageType;
    public array $data;
    public string $logMessage;

    public function __construct(int $sellerId, string $messageType, array $data, string $logMessage = '')
    {
        $this->sellerId = $sellerId;
        $this->messageType = $messageType;
        $this->data = $data;
        $this->logMessage = $logMessage;
    }


    public function broadcastAs(): string
    {
        return $this->messageType;
    }

    public function broadcastWith(): array
    {
        $payload = $this->data;
        
        if ($this->logMessage) {
            $payload['log_message'] = $this->logMessage;
        }
        
        return $payload;
    }

    // Статические методы для разных типов сообщений
    public static function stats(int $sellerId, array $stats = null, string $message = 'Обновлена статистика'): self
    {
        if ($stats === null) {
            $stats = self::getSellerStats($sellerId);
        }
        return new self($sellerId, 'stats', ['stats' => $stats], $message);
    }

    public static function tradeCreated(int $sellerId, array $tradeData): self
    {
        return new self($sellerId, 'trade_created', $tradeData, 'Трейд создан в Steam');
    }

    public static function tradeError(int $sellerId, array $errorData): self
    {
        return new self($sellerId, 'trade_error', $errorData, 'Ошибка создания трейда');
    }

    public static function notification(int $sellerId, string $message, string $type = 'info'): self
    {
        return new self($sellerId, 'notification', ['message' => $message, 'type' => $type], $message);
    }

    // События трейдов
    public static function tradeReserved($orderItem): self
    {
        // Получаем актуальную статистику
        $stats = self::getSellerStats($orderItem->seller_id);
        
        // Генерируем готовую ссылку для Steam трейда
        $tradeUrl = self::generateTradeUrl($orderItem);
        
        return new self($orderItem->seller_id, 'trade_reserved', [
            'trade_url' => $tradeUrl,
            'stats' => $stats,
        ], 'Новый трейд');
    }


    // Метод для принудительного отключения
    public static function forceLogout(string $channel, string $message = 'Токен изменен. Требуется переавторизация.'): self
    {
        // Создаем событие с кастомным каналом
        $event = new self(0, 'force_logout', ['message' => $message], $message);
        
        // Переопределяем канал для конкретного старого канала
        $event->customChannel = $channel;
        
        return $event;
    }

    // Метод для отправки статистики на конкретный канал
    public static function statsToChannel(string $channel, array $stats): self
    {
        $event = new self(0, 'stats', ['stats' => $stats], 'Обновлена статистика');
        $event->customChannel = $channel;
        return $event;
    }

    // Переопределяем broadcastOn для поддержки кастомного канала
    public function broadcastOn(): array
    {
        if (isset($this->customChannel)) {
            return [new Channel($this->customChannel)];
        }
        
        // Генерируем правильный канал с хешем токена
        $client = \App\Models\Client::find($this->sellerId);
        if ($client && $client->extension_token) {
            $hash = substr(hash('sha256', $this->sellerId . $client->extension_token), 0, 16);
            $channel = "seller-{$this->sellerId}-{$hash}";
            return [new Channel($channel)];
        }
        
        // Fallback на старый канал если токен не найден
        return [new Channel('seller-' . $this->sellerId)];
    }
    
    /**
     * Получение статистики продавца
     */
    private static function getSellerStats(int $sellerId): array
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
    
    /**
     * Генерация готовой ссылки для Steam трейда
     */
    private static function generateTradeUrl($orderItem): string
    {
        // Получаем trade URL продавца
        $seller = \App\Models\Client::find($orderItem->seller_id);
        
        if (!$seller || !$seller->steam_trade_url) {
            return null; // Нет trade URL у продавца
        }
        
        // Добавляем параметр trade_id для идентификации
        $tradeUrl = $seller->steam_trade_url;
        $separator = strpos($tradeUrl, '?') !== false ? '&' : '?';
        
        return $tradeUrl . $separator . 'trade_id=' . $orderItem->id;
    }
}