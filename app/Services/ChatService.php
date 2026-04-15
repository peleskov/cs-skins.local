<?php

namespace App\Services;

use App\Models\BannedWord;
use App\Models\ChatBan;
use App\Models\ChatMessage;
use App\Models\Client;
use Illuminate\Support\Facades\Cache;

class ChatService
{
    const THROTTLE_SECONDS = 2;
    const MAX_MESSAGE_LENGTH = 500;

    /**
     * Проверка, забанен ли пользователь в чате
     */
    public function isClientBanned(Client $client): bool
    {
        return ChatBan::where('client_id', $client->id)
            ->active()
            ->exists();
    }

    /**
     * Получить активный бан пользователя
     */
    public function getActiveBan(Client $client): ?ChatBan
    {
        return ChatBan::where('client_id', $client->id)
            ->active()
            ->first();
    }

    /**
     * Проверка throttling для отправки сообщений
     */
    public function checkThrottle(Client $client): bool
    {
        $key = 'chat_throttle_' . $client->id;

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, self::THROTTLE_SECONDS);
        return true;
    }

    /**
     * Получить время до следующего возможного сообщения
     */
    public function getThrottleTime(Client $client): int
    {
        $key = 'chat_throttle_' . $client->id;
        return Cache::has($key) ? self::THROTTLE_SECONDS : 0;
    }

    /**
     * Фильтрация запрещенных слов
     */
    public function filterBannedWords(string $message): string
    {
        $bannedWords = BannedWord::getCachedWords();

        foreach ($bannedWords as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            $replacement = str_repeat('*', mb_strlen($word));
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $message;
    }

    /**
     * Валидация сообщения
     */
    public function validateMessage(string $message): array
    {
        $errors = [];

        if (empty(trim($message))) {
            $errors[] = 'Сообщение не может быть пустым';
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors[] = 'Сообщение слишком длинное (максимум ' . self::MAX_MESSAGE_LENGTH . ' символов)';
        }

        return $errors;
    }

    /**
     * Создать сообщение
     */
    public function createMessage(Client $client, string $message): ChatMessage
    {
        // Фильтрация запрещенных слов
        $filteredMessage = $this->filterBannedWords($message);

        // Создание сообщения
        $chatMessage = ChatMessage::create([
            'client_id' => $client->id,
            'message' => $filteredMessage,
        ]);

        // Очищаем кеш сообщений
        Cache::forget('chat_messages');

        return $chatMessage;
    }

    /**
     * Получить историю сообщений
     */
    public function getMessages(int $limit = 200): array
    {
        return Cache::remember('chat_messages', 10, function () use ($limit) {
            return ChatMessage::with('client:id,name,steam_avatar,avatar_border_color,nickname_color')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values()
                ->toArray();
        });
    }

    /**
     * Забанить пользователя
     */
    public function banClient(Client $client, ?string $until = null, ?string $reason = null, ?Client $bannedBy = null): ChatBan
    {
        // Удаляем старые баны
        ChatBan::where('client_id', $client->id)->delete();

        // Создаем новый бан
        return ChatBan::create([
            'client_id' => $client->id,
            'banned_until' => $until,
            'reason' => $reason,
            'banned_by' => $bannedBy?->id,
        ]);
    }

    /**
     * Разбанить пользователя
     */
    public function unbanClient(Client $client): void
    {
        ChatBan::where('client_id', $client->id)->delete();
    }
}