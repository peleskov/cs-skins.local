<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
        $this->middleware('auth:client');
    }

    /**
     * Получить историю сообщений
     */
    public function getMessages(): JsonResponse
    {
        $messages = $this->chatService->getMessages();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Отправить сообщение
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $client = Auth::guard('client')->user();

        // Проверка бана
        if ($this->chatService->isClientBanned($client)) {
            $ban = $this->chatService->getActiveBan($client);
            return response()->json([
                'success' => false,
                'error' => 'Вы забанены в чате',
                'ban' => [
                    'until' => $ban->banned_until?->toISOString(),
                    'reason' => $ban->reason,
                ],
            ], 403);
        }

        // Проверка throttling
        if (!$this->chatService->checkThrottle($client)) {
            $throttleTime = $this->chatService->getThrottleTime($client);
            return response()->json([
                'success' => false,
                'error' => 'Слишком часто отправляете сообщения',
                'throttle_seconds' => $throttleTime,
            ], 429);
        }

        // Валидация
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $messageText = $request->input('message');

        // Валидация сообщения
        $errors = $this->chatService->validateMessage($messageText);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        // Создание сообщения
        $message = $this->chatService->createMessage($client, $messageText);

        // Отправка события через WebSocket
        $messageData = [
            'message' => $message->message,
            'client_id' => $message->client_id,
            'client_name' => $message->client_name,
            'client_avatar' => $message->client_avatar,
            'created_at' => $message->created_at->toISOString(),
        ];
        broadcast(new ChatMessageSent($messageData));

        return response()->json([
            'success' => true,
            'message' => [
                'message' => $message->message,
                'client_id' => $message->client_id,
                'client_name' => $message->client_name,
                'client_avatar' => $message->client_avatar,
                'created_at' => $message->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Проверить статус бана
     */
    public function checkBanStatus(): JsonResponse
    {
        $client = Auth::guard('client')->user();

        if ($this->chatService->isClientBanned($client)) {
            $ban = $this->chatService->getActiveBan($client);
            return response()->json([
                'success' => true,
                'is_banned' => true,
                'ban' => [
                    'until' => $ban->banned_until?->toISOString(),
                    'reason' => $ban->reason,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'is_banned' => false,
        ]);
    }

    /**
     * Получить количество онлайн пользователей в канале чата
     */
    public function getOnlineCount(): JsonResponse
    {
        try {
            // Используем Broadcasting manager для получения информации о канале
            $broadcaster = app('Illuminate\Broadcasting\BroadcastManager');
            $pusher = $broadcaster->connection('reverb');

            // Попробуем получить информацию о канале через Pusher API
            if (method_exists($pusher, 'get')) {
                $response = $pusher->get('/channels/chat');
                $data = json_decode($response, true);
                $onlineCount = $data['subscription_count'] ?? 0;
            } else {
                $onlineCount = 0;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to get online count: ' . $e->getMessage());
            $onlineCount = 0;
        }

        return response()->json([
            'success' => true,
            'online_count' => $onlineCount,
        ]);
    }

    /**
     * Пользователь подключился к чату или обновил активность
     */
    public function userJoined(): JsonResponse
    {
        $client = Auth::guard('client')->user();
        $userId = $client->id;

        // Используем Redis для отслеживания активных пользователей
        $activeUsersKey = 'chat:active_users';

        // Добавляем пользователя с timestamp
        \Redis::zadd($activeUsersKey, time(), $userId);
        \Redis::expire($activeUsersKey, 120);

        // Удаляем неактивных пользователей (более 60 секунд назад)
        $cutoffTime = time() - 60;
        \Redis::zremrangebyscore($activeUsersKey, 0, $cutoffTime);

        // Получаем актуальное количество
        $newCount = \Redis::zcard($activeUsersKey);

        // Отправляем обновление счетчика всем
        broadcast(new \App\Events\ChatUserCountUpdated($newCount));

        return response()->json(['success' => true, 'count' => $newCount]);
    }

    /**
     * Пользователь отключился от чата
     */
    public function userLeft(): JsonResponse
    {
        $client = Auth::guard('client')->user();
        $userId = $client->id;

        // Удаляем пользователя из активных
        $activeUsersKey = 'chat:active_users';
        \Redis::zrem($activeUsersKey, $userId);

        // Удаляем неактивных пользователей (более 60 секунд назад)
        $cutoffTime = time() - 60;
        \Redis::zremrangebyscore($activeUsersKey, 0, $cutoffTime);

        // Получаем актуальное количество
        $newCount = \Redis::zcard($activeUsersKey);

        // Отправляем обновление счетчика всем
        broadcast(new \App\Events\ChatUserCountUpdated($newCount));

        return response()->json(['success' => true, 'count' => $newCount]);
    }
}