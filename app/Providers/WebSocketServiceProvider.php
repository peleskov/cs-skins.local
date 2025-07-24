<?php

namespace App\Providers;

use App\Services\WebSocketHandler;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WebSocketHandler::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Слушатель для обработки запросов статистики
        $provider = $this;
        Event::listen('extension.stats.requested', function ($sellerId) use ($provider) {
            Log::info('Обработка запроса статистики для продавца', ['seller_id' => $sellerId]);
            
            $stats = $provider->getSellerStats($sellerId);
            
            // Отправляем статистику сразу, так как данные легкие
            Log::info('Отправка статистики через broadcast', ['seller_id' => $sellerId]);
            
            try {
                // Получаем токен клиента для генерации правильного канала
                $client = \App\Models\Client::find($sellerId);
                if ($client && $client->extension_token) {
                    $channel = $this->generateChannel($sellerId, $client->extension_token);
                    
                    // Отправляем напрямую через WebSocket соединение, минуя HTTP API
                    $this->sendMessageDirectly($channel, 'stats', ['stats' => $stats]);
                    Log::info('Статистика отправлена успешно через WebSocket', ['seller_id' => $sellerId, 'channel' => $channel, 'stats' => $stats]);
                } else {
                    Log::warning('Не удалось найти токен клиента для отправки статистики', ['seller_id' => $sellerId]);
                }
            } catch (\Exception $e) {
                Log::warning('Ошибка отправки статистики, но статистика собрана', [
                    'seller_id' => $sellerId, 
                    'error' => $e->getMessage(),
                    'stats' => $stats
                ]);
            }
            
        });
        
        // Обрабатываем WebSocket сообщения
        Event::listen(
            MessageReceived::class,
            function (MessageReceived $event) {
                try {
                    $message = $event->message;
                    
                    // Пропускаем ping события
                    if (is_string($message) && str_contains($message, 'pusher:ping')) {
                        return;
                    }
                    
                    // Логируем ВСЕ WebSocket сообщения от расширения (кроме ping)
                    Log::info('=== WebSocket сообщение получено ===', [
                        'raw_message' => $message,
                        'message_type' => gettype($message),
                        'event_class' => get_class($event),
                        'timestamp' => now()->toISOString()
                    ]);
                    
                    // Парсим сообщение
                    if (is_string($message)) {
                        $data = json_decode($message, true);
                        
                        // Не логируем ping события
                        if ($data && isset($data['event']) && $data['event'] === 'pusher:ping') {
                            return;
                        }
                        
                        Log::info('Распарсенные данные WebSocket:', $data ?: ['error' => 'Failed to parse JSON']);
                        
                        if ($data && isset($data['event']) && $data['event'] === 'client-stats-request') {
                            // Логируем событие обновления на сервере
                            Log::info('Получен запрос на обновление статистики от расширения');
                            
                            // Получаем ID продавца из канала
                            $sellerId = $this->extractSellerIdFromChannel($data['channel'] ?? '');
                            
                            if ($sellerId) {
                                // Проверяем авторизацию через канал
                                if (!$this->isValidChannel($sellerId, $data['channel'])) {
                                    Log::warning('Неавторизованный запрос статистики - неверный канал', [
                                        'seller_id' => $sellerId,
                                        'channel' => $data['channel']
                                    ]);
                                    return;
                                }
                                
                                // Инициируем серверное событие которое обработается асинхронно
                                event('extension.stats.requested', [$sellerId]);
                            }
                        } elseif ($data && isset($data['event']) && $data['event'] === 'pusher:subscribe') {
                            // При подписке на канал отправляем текущую статистику
                            $channel = $data['data']['channel'] ?? null;
                            if ($channel) {
                                $sellerId = $this->extractSellerIdFromChannel($channel);
                                
                                if ($sellerId && $this->isValidChannel($sellerId, $channel)) {
                                    // Отправляем статистику при подключении
                                    $stats = $this->getSellerStats($sellerId);
                                    $this->sendMessageDirectly($channel, 'stats', ['stats' => $stats], 'Расширение подключено');
                                    
                                    Log::info('Отправлена статистика при подключении', [
                                        'seller_id' => $sellerId,
                                        'channel' => $channel
                                    ]);
                                }
                            }
                        } else {
                            Log::info('Неизвестное или неподдерживаемое WebSocket событие:', [
                                'event' => $data['event'] ?? 'not_set',
                                'data' => $data
                            ]);
                        }
                    } else {
                        Log::info('WebSocket сообщение не является строкой:', [
                            'type' => gettype($message),
                            'content' => $message
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('WebSocket error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                }
            }
        );
    }
    
    
    /**
     * Получение статистики продавца
     */
    public function getSellerStats(int $sellerId): array
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
     * Генерация канала на основе seller_id и токена
     */
    public function generateChannel(int $sellerId, string $token): string
    {
        $hash = substr(hash('sha256', $sellerId . $token), 0, 16);
        return "seller-{$sellerId}-{$hash}";
    }
    
    /**
     * Проверка валидности канала
     */
    private function isValidChannel(int $sellerId, string $channel): bool
    {
        $client = \App\Models\Client::find($sellerId);
        
        if (!$client || !$client->extension_token) {
            return false;
        }
        
        $expectedChannel = $this->generateChannel($client->id, $client->extension_token);
        
        return $channel === $expectedChannel;
    }
    
    /**
     * Отправка сообщения напрямую через WebSocket соединение
     */
    private function sendMessageDirectly(string $channel, string $eventType, array $data, string $logMessage = ''): void
    {
        try {
            // Проверяем доступность Reverb (может не работать в queue jobs)
            if (!app()->bound(\Laravel\Reverb\ApplicationManager::class)) {
                Log::warning('Reverb недоступен - пропускаем отправку WebSocket сообщения', [
                    'channel' => $channel,
                    'event' => $eventType
                ]);
                return;
            }
            
            // Получаем приложение через ApplicationManager
            $appManager = app(\Laravel\Reverb\ApplicationManager::class);
            $appProvider = $appManager->driver();
            
            // Получаем приложение по ID из конфига
            $appId = config('reverb.apps.apps.0.id', env('REVERB_APP_ID'));
            $app = $appProvider->findById($appId);
            
            if (!$app) {
                throw new \Exception("Reverb приложение с ID '{$appId}' не найдено");
            }
            
            // Получаем менеджер каналов - проверяем доступность
            if (!app()->bound(\Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager::class)) {
                Log::warning('ChannelManager недоступен - пропускаем отправку WebSocket сообщения', [
                    'channel' => $channel,
                    'event' => $eventType
                ]);
                return;
            }
            
            $channelManager = app(\Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager::class);
            
            // Получаем канал для нашего приложения
            $reverbChannel = $channelManager->for($app)->find($channel);
            
            if ($reverbChannel) {
                // Получаем все соединения канала
                $connections = $reverbChannel->connections();
                
                // Создаем сообщение в формате Pusher, который ожидает расширение
                $messageData = $data;
                if ($logMessage) {
                    $messageData['log_message'] = $logMessage;
                }
                
                $message = json_encode([
                    'event' => $eventType,
                    'data' => json_encode($messageData),
                    'channel' => $channel
                ]);
                
                Log::info('Отправляем сообщение через WebSocket соединения', [
                    'channel' => $channel,
                    'connections_count' => count($connections)
                ]);
                
                // Отправляем сообщение всем подключенным клиентам
                foreach ($connections as $connection) {
                    $connection->connection()->send($message);
                }
                
                Log::info('Сообщение отправлено мгновенно через WebSocket', [
                    'channel' => $channel,
                    'event' => $eventType,
                    'data' => $data
                ]);
            } else {
                Log::warning('Канал не найден или нет подключений', ['channel' => $channel]);
            }
            
        } catch (\Exception $e) {
            Log::error('Ошибка прямой отправки через WebSocket', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Универсальный метод для отправки событий в расширение
     */
    public static function sendToExtension(string $eventType, array $data, string $logMessage = '', ?int $sellerId = null, ?string $channel = null): void
    {
        try {
            $provider = new self(app());
            
            // Если канал не указан, генерируем его из sellerId
            if (!$channel) {
                if (!$sellerId) {
                    Log::error('Не указан ни sellerId ни channel для отправки события', ['event' => $eventType]);
                    return;
                }
                
                $client = \App\Models\Client::find($sellerId);
                if (!$client || !$client->extension_token) {
                    Log::warning('Не удалось найти токен клиента для отправки события', [
                        'seller_id' => $sellerId,
                        'event' => $eventType
                    ]);
                    return;
                }
                
                $channel = $provider->generateChannel($sellerId, $client->extension_token);
            }
            
            $provider->sendMessageDirectly($channel, $eventType, $data, $logMessage);
            
        } catch (\Exception $e) {
            Log::error('Ошибка отправки события в расширение', [
                'seller_id' => $sellerId,
                'channel' => $channel,
                'event' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }
    
}