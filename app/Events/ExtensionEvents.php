<?php

namespace App\Events;

use App\Models\Client;
use Laravel\Reverb\ApplicationManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Exception;
use App\Models\TradeOffer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtensionEvents implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sellerId;
    public string $messageType;
    public array $data;
    public string $logMessage;
    public ?string $customChannel = null;

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

    /**
     * Определяет контекст выполнения и отправляет событие соответствующим способом
     */
    public static function sendSmart(?string $eventType, int $sellerId, array $data, string $logMessage = ''): void
    {
        // Проверяем, находимся ли мы в контексте WebSocket обработчика
        $commandLine = implode(' ', $_SERVER['argv'] ?? []);
        $inWebSocketContext = app()->runningInConsole() && 
                             strpos($commandLine, 'reverb:start') !== false;
        
        
        if ($inWebSocketContext) {
            // В WebSocket контексте отправляем напрямую
            self::sendDirectlyViaWebSocket($eventType, $sellerId, $data, $logMessage);
        } else {
            // В обычном контексте используем broadcast
            $event = new self($sellerId, $eventType, $data, $logMessage);
            broadcast($event);
        }
    }
    
    /**
     * Отправка напрямую через WebSocket соединение (только для WebSocket контекста)
     */
    private static function sendDirectlyViaWebSocket(?string $eventType, int $sellerId, array $data, string $logMessage = ''): void
    {
        try {
            // Находим клиента и его токен
            $client = Client::find($sellerId);
            if (!$client || !$client->extension_token) {
                return;
            }
            
            // Генерируем канал
            $hash = substr(hash('sha256', $sellerId . $client->extension_token), 0, 16);
            $channel = "seller-{$sellerId}-{$hash}";
            
            // Проверяем доступность Reverb
            if (!app()->bound(ApplicationManager::class)) {
                return;
            }
            
            // Получаем Reverb приложение
            $appManager = app(ApplicationManager::class);
            $appProvider = $appManager->driver();
            $appId = config('reverb.apps.apps.0.app_id', env('REVERB_APP_ID'));
            $app = $appProvider->findById($appId);
            
            if (!$app) {
                return;
            }
            
            // Получаем канал и отправляем сообщение
            if (!app()->bound(ChannelManager::class)) {
                return;
            }
            
            $channelManager = app(ChannelManager::class);
            $reverbChannel = $channelManager->for($app)->find($channel);
            
            if ($reverbChannel) {
                // Добавляем log_message к данным
                if ($logMessage) {
                    $data['log_message'] = $logMessage;
                }
                
                $message = json_encode([
                    'event' => $eventType,
                    'data' => json_encode($data),
                    'channel' => $channel
                ]);
                
                foreach ($reverbChannel->connections() as $connection) {
                    $connection->connection()->send($message);
                }
            }
            
        } catch (Exception $e) {
            Log::error('Ошибка прямой отправки через WebSocket', [
                'seller_id' => $sellerId,
                'event' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Статические методы для разных типов сообщений
    public static function stats(int $sellerId, string $message = 'Обновлена статистика'): void
    {
        $stats = self::getSellerStats($sellerId);
        self::sendSmart('stats', $sellerId, ['stats' => $stats], $message);
    }

    // События для TradeOffer
    public static function tradeOfferCreated($tradeOffer): void
    {
        $stats = self::getSellerStats($tradeOffer->seller_id);
        
        $tradeData = [
            'trade_offer_id' => $tradeOffer->id,
            'asset_ids' => $tradeOffer->asset_ids,
            'buyer' => [
                'steam_id' => $tradeOffer->buyer->steam_id,
                'trade_url' => $tradeOffer->buyer_trade_url
            ]
        ];
        
        self::sendSmart('trade_offer_created', $tradeOffer->seller_id, [
            'trade_offer' => $tradeData,
            'stats' => $stats,
        ], 'Получена команда на создание Steam трейда');
    }

    public static function tradeOfferSent($tradeOffer): void
    {
        $stats = self::getSellerStats($tradeOffer->seller_id);
        
        self::sendSmart('trade_offer_sent', $tradeOffer->seller_id, [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
            'stats' => $stats,
        ], 'Трейд отправлен в Steam');
    }

    public static function tradeOfferCompleted($tradeOffer): void
    {
        $stats = self::getSellerStats($tradeOffer->seller_id);
        
        self::sendSmart('trade_offer_completed', $tradeOffer->seller_id, [
            'trade_offer_id' => $tradeOffer->id,
            'stats' => $stats,
        ], 'Трейд завершен');
    }

    public static function tradeOfferCancelled($tradeOffer): void
    {
        $stats = self::getSellerStats($tradeOffer->seller_id);
        
        if ($tradeOffer->steam_trade_offer_id) {
            self::sendSmart('trade_offer_cancelled', $tradeOffer->seller_id, [
                'trade_offer_id' => $tradeOffer->id,
                'stats' => $stats,
            ], 'Трейд отменен');
        } else {
            self::sendSmart(null, $tradeOffer->seller_id, [
                'stats' => $stats,
            ], 'Трейд не создан из-за ошибки Steam');
        }
    }

    /**
     * Команда расширению на отмену Steam трейда
     */
    public static function cancelSteamTrade($tradeOffer): void
    {
        // Получаем информацию о том, откуда вызывается
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = 'unknown';
        
        if (isset($backtrace[1])) {
            $caller = ($backtrace[1]['class'] ?? '') . '::' . ($backtrace[1]['function'] ?? '');
            if (isset($backtrace[2])) {
                $caller .= ' <- ' . ($backtrace[2]['class'] ?? '') . '::' . ($backtrace[2]['function'] ?? '');
            }
        }
        
        // Используем умную отправку
        self::sendSmart('cancel_steam_trade', $tradeOffer->seller_id, [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
            'order_id' => $tradeOffer->order_id
        ], "Получена команда на отмену Steam трейда");
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
    
    /**
     * Ping расширения для проверки доступности
     */
    public static function ping(int $sellerId): void
    {
        self::sendSmart('ping', $sellerId, [
            'timestamp' => now()->toISOString()
        ], 'Проверка доступности расширения');
    }

    // Переопределяем broadcastOn для поддержки кастомного канала
    public function broadcastOn(): array
    {
        if (isset($this->customChannel)) {
            return [new Channel($this->customChannel)];
        }
        
        // Генерируем правильный канал с хешем токена
        $client = Client::find($this->sellerId);
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
        
        // Статистика по TradeOffer за сегодня
        $pendingTrades = TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', TradeOffer::STATUS_PENDING)
            ->count();
            
        $sentTrades = TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', TradeOffer::STATUS_ACTIVE)
            ->count();
        
        $completedTrades = TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', TradeOffer::STATUS_ACCEPTED)
            ->count();
        
        $cancelledTrades = TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', TradeOffer::STATUS_CANCELED)
            ->count();
        
        // Всего трейдов за сегодня
        $totalTradesToday = TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->count();

        return [
            'statistics' => [
                'pending' => $pendingTrades,
                'sent' => $sentTrades,
                'completed' => $completedTrades,
                'cancelled' => $cancelledTrades,
                'total' => $totalTradesToday
            ],
            'updated_at' => now()->toISOString()
        ];
    }
    
}