<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtensionController extends Controller
{
    /**
     * Авторизация расширения с токеном
     */
    public function authenticateExtension(Request $request): JsonResponse
    {
        \Log::info('=== EXTENSION CONTROLLER REACHED ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'all_data' => $request->all(),
            'input_data' => $request->input(),
            'content_type' => $request->header('Content-Type'),
            'raw_content' => $request->getContent(),
        ]);

        // Если данные не извлеклись автоматически, пытаемся парсить JSON вручную
        $token = $request->input('token');
        if (!$token && $request->getContent()) {
            try {
                $json = json_decode($request->getContent(), true);
                if (isset($json['token'])) {
                    $token = $json['token'];
                    \Log::info('Token parsed from JSON manually', ['token' => substr($token, 0, 10) . '...']);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to parse JSON from request body', ['error' => $e->getMessage()]);
            }
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен не указан'
            ], 400);
        }

        try {
            \Log::info('Extension auth attempt', [
                'token' => substr($token, 0, 10) . '...',
                'ip' => $request->ip()
            ]);
            
            // Извлекаем client_id из токена (базовая реализация)
            $clientId = $this->validateExtensionToken($token);
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Недействительный токен'
                ], 401);
            }

            $client = Client::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            // Логируем авторизацию расширения
            Log::info('Extension authorized', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Авторизация успешна',
                'data' => [
                    'client_id' => $client->id,
                    'name' => $client->name,
                    'steam_id' => $client->steam_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Extension authorization error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка авторизации'
            ], 500);
        }
    }

    /**
     * Получение заказов ожидающих обработки
     */
    public function getPendingOrders(Request $request): JsonResponse
    {
        try {
            $clientId = $this->getClientFromToken($request);
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неавторизован'
                ], 401);
            }

            // Получаем заказы где пользователь является продавцом
            // и заказ оплачен, но трейд еще не отправлен
            $orders = Order::where('status', 'paid')
                ->with(['buyer' => function ($query) {
                    $query->select('id', 'name', 'steam_id', 'steam_trade_url');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->filter(function ($order) use ($clientId) {
                    // Фильтруем заказы где есть товары от текущего продавца
                    $cartSnapshot = $order->cart_snapshot;
                    if (!is_array($cartSnapshot)) {
                        return false;
                    }
                    
                    foreach ($cartSnapshot as $item) {
                        if (isset($item['seller_id']) && $item['seller_id'] == $clientId) {
                            return true;
                        }
                    }
                    return false;
                });

            $formattedOrders = $orders->map(function ($order) use ($clientId) {
                // Фильтруем только товары от текущего продавца
                $sellerItems = [];
                foreach ($order->cart_snapshot as $item) {
                    if (isset($item['seller_id']) && $item['seller_id'] == $clientId) {
                        $sellerItems[] = [
                            'listing_id' => $item['listing_id'] ?? null,
                            'steam_asset_id' => $item['steam_asset_id'] ?? null,
                            'market_hash_name' => $item['market_hash_name'] ?? null,
                            'price' => $item['price'] ?? 0,
                            'quantity' => $item['quantity'] ?? 1
                        ];
                    }
                }
                
                return [
                    'id' => $order->id,
                    'total' => $order->total_amount,
                    'currency' => $order->currency,
                    'created_at' => $order->created_at->toISOString(),
                    'buyer' => [
                        'id' => $order->buyer->id,
                        'name' => $order->buyer->name,
                        'steam_id' => $order->buyer->steam_id,
                        'trade_url' => $order->buyer->steam_trade_url
                    ],
                    'items' => $sellerItems
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedOrders
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting pending orders', [
                'error' => $e->getMessage(),
                'client_id' => $this->getClientFromToken($request)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения заказов'
            ], 500);
        }
    }

    /**
     * Обновление статуса трейда
     */
    public function updateTradeStatus(Request $request, $orderId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:trade_sent,completed,error,cancelled',
            'trade_offer_id' => 'nullable|string',
            'error' => 'nullable|string'
        ]);

        try {
            $clientId = $this->getClientFromToken($request);
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неавторизован'
                ], 401);
            }

            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Заказ не найден'
                ], 404);
            }

            // Проверяем, что пользователь является продавцом в этом заказе
            $hasSellerItems = false;
            if (is_array($order->cart_snapshot)) {
                foreach ($order->cart_snapshot as $item) {
                    if (isset($item['seller_id']) && $item['seller_id'] == $clientId) {
                        $hasSellerItems = true;
                        break;
                    }
                }
            }

            if (!$hasSellerItems) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет прав на этот заказ'
                ], 403);
            }

            // Получаем order_items продавца из этого заказа
            $sellerOrderItems = $order->items()
                ->where('seller_id', $clientId)
                ->get();

            if ($sellerOrderItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет товаров в этом заказе'
                ], 404);
            }

            // Маппинг статусов для совместимости с базой данных
            $statusMapping = [
                'trade_sent' => Trade::STATUS_PENDING,
                'completed' => Trade::STATUS_COMPLETED,
                'error' => Trade::STATUS_CANCELLED,
                'cancelled' => Trade::STATUS_CANCELLED
            ];
            
            $dbStatus = $statusMapping[$request->status] ?? Trade::STATUS_CANCELLED;

            // Создаем или обновляем Trade записи для каждого listing_id продавца
            foreach ($sellerOrderItems as $orderItem) {
                Trade::updateOrCreate(
                    ['listing_id' => $orderItem->listing_id],
                    [
                        'buyer_id' => $order->buyer_id,
                        'seller_id' => $clientId,
                        'price' => $orderItem->price,
                        'status' => $dbStatus,
                        'trade_offer_id' => $request->trade_offer_id,
                        'type' => Trade::TYPE_P2P,
                        'initiated_at' => $request->status === 'trade_sent' ? now() : null,
                        'completed_at' => $request->status === 'completed' ? now() : null
                    ]
                );
                
                // Обновляем статус order_item
                if ($request->status === 'trade_sent') {
                    $orderItem->sendTrade();
                } elseif ($request->status === 'completed') {
                    $orderItem->complete();
                } elseif ($request->status === 'cancelled') {
                    $orderItem->cancel();
                    // Освобождаем листинг при отмене
                    if ($orderItem->listing) {
                        $orderItem->listing->activate();
                    }
                }
                // Временно отключаем автоматическую отмену при status === 'error'
            }

            // Обновляем общий статус заказа если нужно
            if ($request->status === 'trade_sent') {
                $order->update(['status' => Order::STATUS_PROCESSING]);
            } elseif ($request->status === 'completed') {
                $order->update(['status' => Order::STATUS_COMPLETED]);
            } elseif ($request->status === 'cancelled') {
                $order->update(['status' => Order::STATUS_CANCELLED]);
            }
            // Временно отключаем автоматическую отмену при status === 'error'

            // Логируем изменение статуса
            Log::info('Trade status updated', [
                'order_id' => $order->id,
                'seller_order_items_count' => $sellerOrderItems->count(),
                'status' => $request->status,
                'client_id' => $clientId,
                'trade_offer_id' => $request->trade_offer_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Статус обновлен',
                'data' => [
                    'updated_items' => $sellerOrderItems->count(),
                    'status' => $request->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating trade status', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'client_id' => $this->getClientFromToken($request)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка обновления статуса'
            ], 500);
        }
    }

    /**
     * Получение информации о пользователе
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        try {
            $clientId = $this->getClientFromToken($request);
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неавторизован'
                ], 401);
            }

            $client = Client::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'steam_id' => $client->steam_id,
                    'steam_avatar' => $client->steam_avatar,
                    'balance' => $client->balance,
                    'is_verified' => $client->is_verified,
                    'created_at' => $client->created_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user info', [
                'error' => $e->getMessage(),
                'client_id' => $this->getClientFromToken($request)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения информации'
            ], 500);
        }
    }

    /**
     * Получение статистики расширения
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $clientId = $this->getClientFromToken($request);
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неавторизован'
                ], 401);
            }

            // Статистика трейдов на основе OrderItems
            $totalTrades = \App\Models\OrderItem::where('seller_id', $clientId)->count();
            
            // Активные трейды (отправленные но не завершенные)
            $activeTrades = \App\Models\OrderItem::where('seller_id', $clientId)
                ->where('status', \App\Models\OrderItem::STATUS_TRADE_SENT)
                ->count();
            
            // Отмененные трейды
            $cancelledTrades = \App\Models\OrderItem::where('seller_id', $clientId)
                ->where('status', \App\Models\OrderItem::STATUS_CANCELLED)
                ->count();
            
            // Трейды за сегодня
            $tradesToday = \App\Models\OrderItem::where('seller_id', $clientId)
                ->whereDate('created_at', today())
                ->count();

            // Успешные трейды
            $successfulTrades = \App\Models\OrderItem::where('seller_id', $clientId)
                ->where('status', \App\Models\OrderItem::STATUS_COMPLETED)
                ->count();

            // Заработок за последние 30 дней
            $recentOrders = Order::where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->get()
                ->filter(function ($order) use ($clientId) {
                    $cartSnapshot = $order->cart_snapshot;
                    if (!is_array($cartSnapshot)) {
                        return false;
                    }
                    
                    foreach ($cartSnapshot as $item) {
                        if (isset($item['seller_id']) && $item['seller_id'] == $clientId) {
                            return true;
                        }
                    }
                    return false;
                });
            
            $recentEarnings = 0;
            foreach ($recentOrders as $order) {
                foreach ($order->cart_snapshot as $item) {
                    if (isset($item['seller_id']) && $item['seller_id'] == $clientId) {
                        $recentEarnings += $item['price'] * ($item['quantity'] ?? 1);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'totalTrades' => $totalTrades,
                    'activeTrades' => $activeTrades,
                    'cancelledTrades' => $cancelledTrades,
                    'tradesToday' => $tradesToday,
                    'successfulTrades' => $successfulTrades,
                    'recentEarnings' => $recentEarnings,
                    'successRate' => $totalTrades > 0 ? round(($successfulTrades / $totalTrades) * 100, 1) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting extension stats', [
                'error' => $e->getMessage(),
                'client_id' => $this->getClientFromToken($request)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения статистики'
            ], 500);
        }
    }

    /**
     * Отправка телеметрии
     */
    public function sendTelemetry(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string',
            'data' => 'nullable|array',
            'timestamp' => 'required|date',
            'user_agent' => 'nullable|string'
        ]);

        try {
            $clientId = $this->getClientFromToken($request);

            // Логируем телеметрию (можно расширить до отдельной таблицы)
            Log::info('Extension telemetry', [
                'client_id' => $clientId,
                'event_type' => $request->event_type,
                'data' => $request->data,
                'timestamp' => $request->timestamp,
                'user_agent' => $request->user_agent,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Телеметрия отправлена'
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending telemetry', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка отправки телеметрии'
            ], 500);
        }
    }

    /**
     * Проверка связи
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Получение конфигурации
     */
    public function getConfig(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'api_url' => config('app.url'),
                'polling_interval' => 5000, // 5 секунд
                'max_retries' => 3,
                'trade_timeout' => 300000, // 5 минут
                'notification_enabled' => true,
                'api_version' => '1.0.0',
                'sse_enabled' => true,
                'sse_reconnect_delay' => 5000 // 5 секунд
            ]
        ]);
    }

    /**
     * Валидация токена расширения
     * Базовая реализация - в продакшене нужно использовать JWT или другой безопасный метод
     */
    private function validateExtensionToken(string $token): ?int
    {
        // Ищем токен в базе данных
        $client = Client::where('extension_token', $token)->first();
        
        if (!$client) {
            return null;
        }

        return $client->id;
    }

    /**
     * Получение ID клиента из токена в заголовке
     */
    private function getClientFromToken(Request $request): ?int
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7); // Убираем "Bearer "
        return $this->validateExtensionToken($token);
    }
}