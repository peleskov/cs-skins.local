<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\Trade;
use App\Providers\WebSocketServiceProvider;
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
        // Если данные не извлеклись автоматически, пытаемся парсить JSON вручную
        $token = $request->input('token');
        if (!$token && $request->getContent()) {
            try {
                $json = json_decode($request->getContent(), true);
                if (isset($json['token'])) {
                    $token = $json['token'];
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse JSON from request body', ['error' => $e->getMessage()]);
            }
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен не указан'
            ], 400);
        }

        try {
            // Логируем попытку авторизации
            Log::info('Extension authorization attempt', [
                'token_prefix' => substr($token, 0, 10) . '...',
                'token_length' => strlen($token),
                'ip' => $request->ip()
            ]);
            
            // Извлекаем client_id из токена (базовая реализация)
            $clientId = $this->validateExtensionToken($token);

            if (!$clientId) {
                Log::warning('Extension authorization failed - invalid token', [
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'ip' => $request->ip()
                ]);
                
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

            // Генерируем уникальный канал для WebSocket
            $channel = $this->generateChannel($client->id, $token);

            // Логируем авторизацию расширения
            Log::info('Extension authorized', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'channel' => $channel,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Авторизация успешна',
                'channel' => $channel,
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

    /**
     * Генерация канала на основе seller_id и токена
     */
    private function generateChannel(int $sellerId, string $token): string
    {
        $hash = substr(hash('sha256', $sellerId . $token), 0, 16);
        return "seller-{$sellerId}-{$hash}";
    }
    
    /**
     * Логирование ошибок из расширения
     */
    public function logError(Request $request): JsonResponse
    {
        try {
            // Проверяем авторизацию через Bearer токен
            $client = $this->getAuthenticatedClient($request);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $data = $request->validate([
                'type' => 'required|string',
                'message' => 'required|string',
                'context' => 'nullable|array',
                'timestamp' => 'nullable|string'
            ]);
            
            // Логируем ошибку в специальный канал
            Log::channel('extension_errors')->error('Extension Error', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'type' => $data['type'],
                'message' => $data['message'],
                'context' => $data['context'] ?? [],
                'timestamp' => $data['timestamp'] ?? now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Error logged successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to log extension error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to log error'
            ], 500);
        }
    }
    
    /**
     * Получить авторизованного клиента из Bearer токена
     */
    private function getAuthenticatedClient(Request $request): ?Client
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }
        
        // Ищем клиента по токену
        return Client::where('extension_token', $token)->first();
    }
}
