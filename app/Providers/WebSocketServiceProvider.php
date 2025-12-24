<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Слушатель для обработки запросов статистики
        $provider = $this;
        Event::listen('extension.stats.requested', function ($sellerId) use ($provider) {
            $stats = $provider->getSellerStats($sellerId);

            try {
                $client = \App\Models\Client::find($sellerId);
                if ($client && $client->extension_token) {
                    \App\Events\ExtensionEvents::sendSmart('stats', $sellerId, ['stats' => $stats], 'Обновлена статистика');
                } else {
                    Log::warning('Не удалось найти токен клиента для отправки статистики', ['seller_id' => $sellerId]);
                }
            } catch (\Exception $e) {
                Log::warning('Ошибка отправки статистики', [
                    'seller_id' => $sellerId,
                    'error' => $e->getMessage()
                ]);
            }
        });

        // Обрабатываем WebSocket сообщения
        Event::listen(
            MessageReceived::class,
            function (MessageReceived $event) {
                try {
                    $message = $event->message;

                    // Логируем все сообщения от каналов seller-*
                    if (is_string($message) && str_contains($message, 'seller-')) {
                        $preview = strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message;
                        //Log::info('Raw WebSocket message from seller-* channel', ['message' => $preview]);
                    }

                    // Пропускаем ping события
                    if (is_string($message) && str_contains($message, 'pusher:ping')) {
                        return;
                    }

                    // Парсим сообщение
                    if (is_string($message)) {
                        $data = json_decode($message, true);

                        // Не логируем ping события
                        if ($data && isset($data['event']) && $data['event'] === 'pusher:ping') {
                            return;
                        }

                        if ($data && isset($data['event']) && $data['event'] === 'extension-message') {

                            // Получаем ID продавца из канала
                            $sellerId = $this->extractSellerIdFromChannel($data['channel'] ?? '');

                            // Логируем extension-message от всех seller
                            /*
                            if (str_contains($data['channel'] ?? '', 'seller-')) {
                                Log::info($data['event'] . ' received', [
                                    'channel' => $data['channel'] ?? 'no_channel',
                                    'extracted_seller_id' => $sellerId,
                                    'message_type' => $data['data']['type'] ?? 'no_type'
                                ]);
                            }
                            */

                            if ($sellerId) {
                                // Проверяем авторизацию через канал
                                if (!$this->isValidChannel($sellerId, $data['channel'])) {
                                    Log::warning('Неавторизованное сообщение от расширения - неверный канал', [
                                        'seller_id' => $sellerId,
                                        'channel' => $data['channel']
                                    ]);
                                    return;
                                }

                                // Обрабатываем разные типы сообщений, передаем канал для прямого ответа
                                $this->handleClientMessage($sellerId, $data['data'] ?? [], $data['channel'] ?? null);
                            } else {
                                Log::warning('Не удалось извлечь seller_id из канала', [
                                    'channel' => $data['channel'] ?? 'not_set'
                                ]);
                            }

                            // ВАЖНО: завершаем обработку для ВСЕХ client-message событий
                            return;
                        } elseif ($data && isset($data['event']) && $data['event'] === 'client-stats-request') {
                            // Старый обработчик для совместимости
                            $sellerId = $this->extractSellerIdFromChannel($data['channel'] ?? '');

                            if ($sellerId) {
                                if (!$this->isValidChannel($sellerId, $data['channel'])) {
                                    Log::warning('Неавторизованный запрос статистики', [
                                        'seller_id' => $sellerId,
                                        'channel' => $data['channel']
                                    ]);
                                    return;
                                }
                                event('extension.stats.requested', [$sellerId]);
                            }
                        } elseif ($data && isset($data['event']) && $data['event'] === 'pusher:subscribe') {
                            // При подписке на канал отправляем текущую статистику
                            $channel = $data['data']['channel'] ?? null;
                            if ($channel) {
                                $sellerId = $this->extractSellerIdFromChannel($channel);

                                // Логируем подписки от всех seller
                                if (str_contains($channel, 'seller-')) {
                                    /*
                                    Log::info('pusher:subscribe received', [
                                        'channel' => $channel,
                                        'seller_id' => $sellerId,
                                        'is_valid' => $sellerId ? $this->isValidChannel($sellerId, $channel) : false
                                    ]);
                                    */
                                }

                                if ($sellerId && $this->isValidChannel($sellerId, $channel)) {
                                    \App\Events\ExtensionEvents::sendSmart('connected', $sellerId, ['status' => 'ok'], 'Расширение подключено');
                                }
                            }
                        }
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

        // Статистика по TradeOffer за сегодня
        $pendingTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_PENDING)
            ->count();

        $sentTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_ACTIVE)
            ->count();

        $completedTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_ACCEPTED)
            ->count();

        $cancelledTrades = \App\Models\TradeOffer::where('seller_id', $sellerId)
            ->whereDate('created_at', $today)
            ->where('status', \App\Models\TradeOffer::STATUS_CANCELED)
            ->count();

        // Всего трейдов за сегодня
        $totalTradesToday = \App\Models\TradeOffer::where('seller_id', $sellerId)
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
     * Обработка сообщений от расширения
     */
    private function handleClientMessage(int $sellerId, array $messageData, ?string $channel = null): void
    {
        // Распаковываем сжатые данные если нужно
        if (isset($messageData['encoding']) && $messageData['encoding'] === 'gzip') {
            try {
                $compressed = $messageData['compressed'];
                $compressedBytes = pack('C*', ...$compressed);
                $decompressed = gzdecode($compressedBytes);
                $decompressedData = json_decode($decompressed, true);
                
                // Сохраняем оригинальные поля и добавляем декомпрессированные данные
                $messageData = array_merge($messageData, $decompressedData);
                unset($messageData['compressed'], $messageData['encoding']);

                /*
                Log::info('Decompressed message', [
                    'seller_id' => $sellerId,
                    'original_size' => $messageData['original_size'] ?? 'unknown',
                    'compressed_size' => $messageData['compressed_size'] ?? 'unknown',
                    'decompressed_size' => strlen($decompressed)
                ]);
                */
            } catch (\Exception $e) {
                Log::error('Failed to decompress message', [
                    'seller_id' => $sellerId,
                    'error' => $e->getMessage()
                ]);
                $this->handleCompressionError($sellerId);
                return;
            }
        }

        $messageType = $messageData['type'] ?? null;

        if (!$messageType) {
            Log::warning('Получено сообщение от расширения без типа', [
                'seller_id' => $sellerId,
                'data' => $messageData
            ]);
            return;
        }


        switch ($messageType) {
            case 'session_data':
                $this->handleSessionData($sellerId, $messageData, $channel);
                break;

            case 'stats_request':
                // Запрос статистики - если канал передан, отвечаем напрямую
                if ($channel) {
                    $stats = $this->getSellerStats($sellerId);
                    \App\Events\ExtensionEvents::sendSmart('stats', $sellerId, ['stats' => $stats], 'Обновлена статистика');
                } else {
                    event('extension.stats.requested', [$sellerId]);
                }
                break;

            case 'trade_offer_sent':
                // Трейд успешно создан в Steam
                $this->handleTradeOfferSent($sellerId, $messageData);
                break;

            case 'trade_offer_failed':
                // Ошибка создания трейда
                $this->handleTradeOfferFailed($sellerId, $messageData);
                break;

            case 'error_log':
                // Логирование ошибок для отладки
                $this->handleErrorLog($sellerId, $messageData);
                break;

            case 'steam_trade_cancelled':
                // Ответ об отмене Steam трейда
                $this->handleSteamTradeCancelled($sellerId, $messageData);
                break;

            case 'steam_api_error':
                // Логирование ошибок Steam API для анализа
                $this->handleSteamApiError($sellerId, $messageData);
                break;

            case 'pong':
                // Ответ на ping - расширение доступно
                $this->handlePong($sellerId, $messageData);
                break;

            default:
                Log::warning('Неизвестный тип сообщения от расширения', [
                    'seller_id' => $sellerId,
                    'type' => $messageType,
                    'data' => $messageData
                ]);
        }
    }

    /**
     * Обработка данных Steam сессии от расширения
     */
    private function handleSessionData(int $sellerId, array $data, ?string $channel = null): void
    {
        // Логируем обработку сессий от всех seller
        if (str_contains($channel ?? '', 'seller-')) {
            //Log::info('handleSessionData called', ['seller_id' => $sellerId, 'channel' => $channel]);
        }

        // Извлекаем данные из правильной структуры
        $actualData = $data;
        if (isset($data['data']) && isset($data['encoding'])) {
            // Данные после обработки compressData (сжатые или нет)
            $actualData = $data['data'];
        }


        if (!isset($actualData['session'])) {
            Log::warning('Session data missing session field', ['seller_id' => $sellerId, 'structure' => array_keys($actualData)]);
            return;
        }

        $sessionData = $actualData['session'];

        // Обрабатываем трейды если они есть
        if (isset($actualData['trades']) && is_array($actualData['trades'])) {
            $tradeService = app(\App\Services\Steam\TradeService::class);
            $tradeService->updateTradeStatuses($sellerId, $actualData['trades']);
        }


        if (!isset($sessionData['sessionid']) || !isset($sessionData['steamid'])) {
            Log::warning('Invalid session data structure', [
                'seller_id' => $sellerId,
                'has_sessionid' => isset($sessionData['sessionid']),
                'has_steamid' => isset($sessionData['steamid'])
            ]);
            return;
        }

        $client = \App\Models\Client::find($sellerId);
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

        $sessionCache = app(\App\Services\Steam\SessionCache::class);
        $success = $sessionCache->set($sellerId, $sessionData);

        if ($success) {
            // Отмечаем продавца как онлайн (TTL 30 секунд)
            Redis::zadd('online_sellers', now()->timestamp + 30, $sellerId);

            \App\Events\ExtensionEvents::sendSmart('session_received', $sellerId, [
                'status' => 'success',
                'expires_in' => $sessionCache->getExpiresInSeconds($sellerId)
            ], 'Steam сессия получена и кеширована');
        } else {
            Log::error('Failed to cache session data', ['seller_id' => $sellerId]);
        }
    }

    /**
     * Обработка успешного создания трейда
     */
    private function handleTradeOfferSent(int $sellerId, array $data): void
    {
        $tradeOfferId = $data['trade_offer_id'] ?? null;
        $steamTradeOfferId = $data['steam_trade_offer_id'] ?? null;

        if (!$tradeOfferId || !$steamTradeOfferId) {
            Log::warning('Неполные данные для обновления трейд оффера', [
                'seller_id' => $sellerId,
                'data' => $data
            ]);
            return;
        }

        try {
            $tradeOffer = \App\Models\TradeOffer::find($tradeOfferId);

            if ($tradeOffer) {
                // Обновляем TradeOffer без broadcast
                $tradeOffer->update([
                    'status' => \App\Models\TradeOffer::STATUS_ACTIVE,
                    'steam_trade_offer_id' => $steamTradeOfferId,
                    'is_ready' => false,
                ]);

                // Активируем следующий TradeOffer
                \App\Models\TradeOffer::where('seller_id', $sellerId)
                    ->where('status', \App\Models\TradeOffer::STATUS_PENDING)
                    ->where('is_ready', false)
                    ->orderBy('created_at', 'asc')
                    ->limit(1)
                    ->update(['is_ready' => true]);
            } else {
                Log::warning('TradeOffer не найден для обновления', [
                    'trade_offer_id' => $tradeOfferId,
                    'seller_id' => $sellerId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка обновления TradeOffer', [
                'trade_offer_id' => $tradeOfferId,
                'seller_id' => $sellerId,
                'error' => $e->getMessage()
            ]);
        }

        // Отправляем подтверждение после обновления базы (не блокирует)
        if ($tradeOfferId && $steamTradeOfferId) {
        }
    }

    /**
     * Обработка ошибки создания трейда
     */
    private function handleTradeOfferFailed(int $sellerId, array $data): void
    {
        $tradeOfferId = $data['trade_offer_id'] ?? null;
        $error = $data['error'] ?? 'Unknown error';

        if (!$tradeOfferId) {
            Log::warning('Нет ID трейд оффера для отметки как failed', [
                'seller_id' => $sellerId,
                'data' => $data
            ]);
            return;
        }

        try {
            $tradeOffer = \App\Models\TradeOffer::find($tradeOfferId);

            if ($tradeOffer) {
                // Отменяем весь заказ через централизованный метод
                $order = $tradeOffer->order;
                if ($order) {
                    $order->cancel('Не удалось создать трейд-предложение. Возможно, продавец временно недоступен или превышен лимит трейдов.');
                }

                Log::info('TradeOffer отменен из-за ошибки', [
                    'trade_offer_id' => $tradeOfferId,
                    'error' => $error,
                    'seller_id' => $sellerId
                ]);
            } else {
                Log::warning('TradeOffer не найден для отмены', [
                    'trade_offer_id' => $tradeOfferId,
                    'seller_id' => $sellerId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка отмены TradeOffer', [
                'trade_offer_id' => $tradeOfferId,
                'seller_id' => $sellerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Обработка логов ошибок для отладки
     */
    private function handleErrorLog(int $sellerId, array $data): void
    {
        $errorType = $data['type'] ?? 'unknown';
        $message = $data['message'] ?? 'No message';
        $context = $data['context'] ?? [];
        $timestamp = $data['timestamp'] ?? now()->toISOString();

        Log::info("Extension Error Log [{$errorType}]", [
            'seller_id' => $sellerId,
            'error_type' => $errorType,
            'message' => $message,
            'context' => $context,
            'extension_timestamp' => $timestamp,
            'server_timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Обработка ответа об отмене Steam трейда
     */
    private function handleSteamTradeCancelled(int $sellerId, array $data): void
    {
        $tradeOfferId = $data['trade_offer_id'] ?? null;
        $success = $data['success'] ?? false;
        $error = $data['error'] ?? null;

        // Логируем только неуспешные отмены
        if (!$success) {
            Log::warning('Ошибка отмены Steam трейда', [
                'seller_id' => $sellerId,
                'trade_offer_id' => $tradeOfferId,
                'error' => $error
            ]);
        }

        if ($tradeOfferId) {
            $tradeOffer = \App\Models\TradeOffer::find($tradeOfferId);

            if ($tradeOffer) {
                if ($success) {
                    $order = $tradeOffer->order;
                    $order->cancel('Steam трейд отменен по истечению резерва');

                    // Отправляем подтверждение в расширение
                    \App\Events\ExtensionEvents::tradeOfferCancelled($tradeOffer);
                } else {
                    Log::error('Не удалось отменить Steam трейд', [
                        'trade_offer_id' => $tradeOfferId,
                        'error' => $error
                    ]);
                }
            }
        }
    }

    /**
     * Обработка ошибок Steam API для анализа
     */
    private function handleSteamApiError(int $sellerId, array $data): void
    {
        $operation = $data['operation'] ?? 'unknown';
        $url = $data['url'] ?? 'unknown';
        $httpStatus = $data['httpStatus'] ?? null;
        $rawResponse = $data['rawResponse'] ?? null;
        $error = $data['error'] ?? 'No error message';
        $extensionVersion = $data['extension_version'] ?? 'unknown';

        Log::warning("Steam API Error: {$operation}", [
            'seller_id' => $sellerId,
            'operation' => $operation,
            'http_status' => $httpStatus,
            'error' => $error,
            'raw_response' => $rawResponse
        ]);

        // Можно также сохранить в базу данных для анализа паттернов ошибок
        // или отправить в monitoring систему
    }

    /**
     * Обработка ответа на ping от расширения
     */
    private function handlePong(int $sellerId, array $data): void
    {
        $timestamp = $data['timestamp'] ?? null;
        $steamAvailable = $data['steam_available'] ?? false;
        $steamState = $data['steam_state'] ?? 'unknown';
        $steamReason = $data['steam_reason'] ?? 'unknown';

        Log::info('Extension pong received', [
            'seller_id' => $sellerId,
            'timestamp' => $timestamp,
            'steam_available' => $steamAvailable,
            'steam_state' => $steamState,
            'steam_reason' => $steamReason,
            'response_time' => $timestamp ? now()->diffInMilliseconds($timestamp) : null
        ]);

        // Сохраняем информацию о готовности только если все готово
        if ($steamAvailable) {
            Cache::put("extension_available_{$sellerId}", true, 60); // 60 секунд
        }
        // Если не готов - просто не сохраняем в кеш, Job повторится
    }

    /**
     * Обработка ошибок компрессии - отправляем команду на перезагрузку расширения
     */
    private function handleCompressionError(int $sellerId): void
    {
        $errorKey = "compression_errors_{$sellerId}";
        $errorCount = Cache::get($errorKey, 0) + 1;
        
        // Увеличиваем счетчик ошибок
        Cache::put($errorKey, $errorCount, 300); // 5 минут
        
        // Отправляем команду на перезагрузку после 3 ошибок подряд
        if ($errorCount >= 3) {
            Log::warning('Sending reload command to extension due to repeated compression errors', [
                'seller_id' => $sellerId,
                'error_count' => $errorCount
            ]);
            
            \App\Events\ExtensionEvents::sendSmart('reload_extension', $sellerId, [], '');
            
            // Очищаем счетчик после отправки команды
            Cache::forget($errorKey);
        }
    }
}
