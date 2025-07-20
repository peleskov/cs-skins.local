<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\VerifyEmailNotification;

class ProfileController extends Controller
{
    /**
     * Страница профиля пользователя
     */
    public function index()
    {
        $client = auth('client')->user();
        $telegramBotName = env('TELEGRAM_BOT_NAME');
        
        // Проверяем статус Telegram авторизации если пользователь верифицирован
        if ($client->is_verified && $client->telegram_id) {
            $this->checkTelegramAuthorization($client);
        }
        
        // Переводы для табов профиля
        $profileTabs = __('profile.tabs');
        
        return view('profile.index', compact('client', 'telegramBotName', 'profileTabs'));
    }

    /**
     * Обновление email адреса
     */
    public function updateEmail(Request $request)
    {
        $client = auth('client')->user();
        
        $request->validate([
            'email' => 'required|email|unique:clients,email,' . $client->id
        ], [
            'email.required' => 'Email адрес обязателен для заполнения',
            'email.email' => 'Введите корректный email адрес',
            'email.unique' => 'Этот email адрес уже используется'
        ]);

        $isNewEmail = !$client->email;
        $isEmailChanged = $client->email && $client->email !== $request->email;
        
        if ($isNewEmail || $isEmailChanged) {
            $client->email = $request->email;
            $client->email_verified_at = null;
            $client->email_verification_sent_at = now();
            $client->save();
            
            // Отправляем письмо с верификацией
            $client->notify(new VerifyEmailNotification());
            
            if ($isNewEmail) {
                return redirect()->route('profile')->with('success', 'Email адрес добавлен. Проверьте почту для подтверждения.');
            } else {
                return redirect()->route('profile')->with('success', 'Email адрес изменен. Проверьте почту для подтверждения.');
            }
        }
        
        return redirect()->route('profile');
    }

    /**
     * Подтверждение email адреса
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $client = auth('client')->user();
        
        \Log::info('Email verification attempt', [
            'user_id' => $client->id,
            'url_id' => $id,
            'url_hash' => $hash,
            'user_email_hash' => sha1($client->email),
            'has_valid_signature' => $request->hasValidSignature()
        ]);
        
        // Временно отключаем проверку подписи для отладки
        // if (!$request->hasValidSignature()) {
        //     return redirect()->route('profile')->with('error', 'Ссылка для подтверждения недействительна или устарела.');
        // }
        
        if ($client->id != $id || sha1($client->email) !== $hash) {
            return redirect()->route('profile')->with('error', 'Неверная ссылка для подтверждения.');
        }
        
        if ($client->hasVerifiedEmail()) {
            return redirect()->route('profile')->with('info', 'Email уже подтвержден.');
        }
        
        $client->email_verified_at = now();
        $client->save();
        
        return redirect()->route('profile')->with('success', 'Email адрес успешно подтвержден!');
    }

    /**
     * Обновление Trade URL
     */
    public function updateTradeUrl(Request $request)
    {
        $client = auth('client')->user();
        
        $request->validate([
            'trade_url' => 'required|url',
        ]);
        
        $tradeUrl = $request->trade_url;
        
        // Валидация Trade URL (формат + соответствие Steam ID)
        $validation = \App\Models\Client::validateTradeUrl($tradeUrl, $client->steam_id);
        
        if (!$validation['valid']) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $validation['message']], 422);
            }
            return redirect()->route('profile')->with('error', $validation['message']);
        }
        
        $client->steam_trade_url = $tradeUrl;
        $client->save();
        
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Trade URL успешно обновлен!']);
        }
        
        return redirect()->route('profile')->with('success', 'Trade URL успешно обновлен!');
    }

    /**
     * Повторная отправка письма с подтверждением
     */
    public function resendVerification(Request $request)
    {
        $client = auth('client')->user();
        
        if (!$client->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email адрес не указан.'
            ], 400);
        }
        
        if ($client->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email уже подтвержден.'
            ], 400);
        }
        
        if (!$client->canResendVerificationEmail()) {
            $time = $client->formattedTimeUntilCanResend();
            return response()->json([
                'success' => false,
                'message' => "Повторная отправка будет доступна через {$time}."
            ], 429);
        }
        
        $client->email_verification_sent_at = now();
        $client->save();
        $client->refresh();
        
        $client->notify(new VerifyEmailNotification());
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Письмо с подтверждением отправлено повторно.'
            ]);
        }
        
        return redirect()->route('profile')->with('success', 'Письмо с подтверждением отправлено повторно.');
    }

    /**
     * Верификация через Telegram
     */
    public function verifyTelegram(Request $request)
    {
        $client = auth('client')->user();
        
        // Получаем данные от Telegram
        $telegramData = $request->all();
        
        \Log::info('Telegram verification attempt:', $telegramData);
        
        // Проверка подлинности данных от Telegram
        $telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        if (!$telegramBotToken) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram верификация не настроена.'
                ], 500);
            }
            return redirect()->route('profile')->with('error', 'Telegram верификация не настроена.');
        }
        
        // Проверка наличия обязательных полей
        if (!isset($telegramData['id']) || !isset($telegramData['auth_date']) || !isset($telegramData['hash'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Отсутствуют обязательные данные от Telegram.'
                ], 400);
            }
            return redirect()->route('profile')->with('error', 'Отсутствуют обязательные данные от Telegram.');
        }
        
        // Создаем строку для проверки hash (по образцу официального примера)
        $checkHash = $telegramData['hash'];
        unset($telegramData['hash']);
        
        $dataCheckArr = [];
        foreach ($telegramData as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);
        
        $secretKey = hash('sha256', $telegramBotToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
        
        \Log::info('Hash verification:', [
            'data_check_string' => $dataCheckString,
            'calculated_hash' => $hash,
            'received_hash' => $checkHash,
            'match' => strcmp($hash, $checkHash) === 0
        ]);
        
        if (strcmp($hash, $checkHash) !== 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверные данные от Telegram.'
                ], 400);
            }
            return redirect()->route('profile')->with('error', 'Неверные данные от Telegram.');
        }
        
        // Проверка что данные не устарели (не старше 24 часов)
        $currentTime = time();
        $authTime = (int)$telegramData['auth_date'];
        $timeDiff = $currentTime - $authTime;
        
        \Log::info('Time verification:', [
            'current_time' => $currentTime,
            'auth_time' => $authTime,
            'time_diff' => $timeDiff,
            'is_expired' => $timeDiff > 86400
        ]);
        
        if ($timeDiff > 86400) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Данные от Telegram устарели. Попробуйте еще раз.'
                ], 400);
            }
            return redirect()->route('profile')->with('error', 'Данные от Telegram устарели. Попробуйте еще раз.');
        }
        
        // Проверка что этот Telegram ID не используется другим пользователем
        $existingClient = \App\Models\Client::where('telegram_id', $telegramData['id'])
            ->where('id', '!=', $client->id)
            ->first();
            
        if ($existingClient) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот Telegram аккаунт уже привязан к другому пользователю.'
                ], 400);
            }
            return redirect()->route('profile')->with('error', 'Этот Telegram аккаунт уже привязан к другому пользователю.');
        }
        
        // Сохраняем данные Telegram и устанавливаем верификацию
        $client->telegram_id = $telegramData['id'];
        $client->telegram_username = $telegramData['username'] ?? null;
        $client->is_verified = true;
        $client->save();
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Telegram верификация успешно завершена!'
            ]);
        }
        
        return redirect()->route('profile')->with('success', 'Telegram верификация успешно завершена!');
    }

    /**
     * Отвязка Telegram аккаунта
     */
    public function unlinkTelegram(Request $request)
    {
        $client = auth('client')->user();
        
        // Проверяем что Telegram привязан
        if (!$client->telegram_id) {
            return redirect()->route('profile')->with('error', 'Telegram аккаунт не привязан.');
        }
        
        // Отвязываем Telegram и сбрасываем верификацию
        $client->telegram_id = null;
        $client->telegram_username = null;
        $client->is_verified = false;
        $client->save();
        
        return redirect()->route('profile')->with('success', 'Telegram аккаунт успешно отвязан. Статус верификации сброшен.');
    }

    /**
     * Webhook для обработки событий от Telegram
     */
    public function telegramWebhook(Request $request)
    {
        $telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        if (!$telegramBotToken) {
            return response('Bot token not configured', 400);
        }

        // Проверяем заголовок X-Telegram-Bot-Api-Secret-Token если он настроен
        $secretToken = env('TELEGRAM_WEBHOOK_SECRET');
        if ($secretToken && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secretToken) {
            return response('Unauthorized', 401);
        }

        $update = $request->all();
        \Log::info('Telegram webhook received:', $update);

        // Проверяем отзыв авторизации (when user revokes login widget access)
        if (isset($update['revoked_auth'])) {
            $revokedAuth = $update['revoked_auth'];
            $userId = $revokedAuth['user_id'] ?? null;
            
            if ($userId) {
                \Log::info("Login widget authorization revoked for user: {$userId}");
                $this->handleTelegramRevocation($userId);
            }
        }

        // Проверяем есть ли информация об отозванной авторизации
        if (isset($update['my_chat_member'])) {
            $chatMember = $update['my_chat_member'];
            $userId = $chatMember['from']['id'] ?? null;
            $newStatus = $chatMember['new_chat_member']['status'] ?? null;

            // Если бот был заблокирован или удален
            if ($userId && in_array($newStatus, ['kicked', 'left'])) {
                $this->handleTelegramRevocation($userId);
            }
        }

        // Обрабатываем команды бота
        if (isset($update['message'])) {
            $message = $update['message'];
            $userId = $message['from']['id'] ?? null;
            $text = $message['text'] ?? '';

            // Команда для отвязки аккаунта
            if ($text === '/revoke' && $userId) {
                $this->handleTelegramRevocation($userId);
                
                // Отправляем ответ пользователю
                $this->sendTelegramMessage($userId, 'Ваш аккаунт был отвязан от сайта.');
            }
        }

        return response('OK', 200);
    }

    /**
     * Обработка отзыва авторизации Telegram
     */
    private function handleTelegramRevocation($telegramUserId)
    {
        $client = \App\Models\Client::where('telegram_id', $telegramUserId)->first();
        
        if ($client) {
            \Log::info("Revoking Telegram authorization for user {$client->id}, telegram_id: {$telegramUserId}");
            
            $client->telegram_id = null;
            $client->telegram_username = null;
            $client->is_verified = false;
            $client->save();
        }
    }

    /**
     * Отправка сообщения в Telegram
     */
    private function sendTelegramMessage($userId, $message)
    {
        $telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        if (!$telegramBotToken) {
            return;
        }

        $url = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";
        
        try {
            \Http::post($url, [
                'chat_id' => $userId,
                'text' => $message
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send Telegram message: ' . $e->getMessage());
        }
    }

    /**
     * Проверка статуса Telegram авторизации
     */
    private function checkTelegramAuthorization($client)
    {
        $telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        if (!$telegramBotToken || !$client->telegram_id) {
            return;
        }

        try {
            // Пытаемся получить информацию о чате с пользователем
            $url = "https://api.telegram.org/bot{$telegramBotToken}/getChat";
            $response = \Http::timeout(5)->get($url, [
                'chat_id' => $client->telegram_id
            ]);

            $data = $response->json();
            
            \Log::info('Telegram auth check for user ' . $client->id, [
                'telegram_id' => $client->telegram_id,
                'response' => $data
            ]);

            // Если получили ошибку "Forbidden: bot was blocked by the user" или подобную
            if (!$data['ok']) {
                $errorDescription = $data['description'] ?? '';
                
                // Проверяем на типичные ошибки блокировки/отзыва
                $blockedErrors = [
                    'bot was blocked by the user',
                    'user is deactivated',
                    'chat not found',
                    'bot can\'t initiate conversation'
                ];
                
                foreach ($blockedErrors as $error) {
                    if (stripos($errorDescription, $error) !== false) {
                        \Log::info("Telegram authorization revoked for user {$client->id}: {$errorDescription}");
                        
                        // Отвязываем пользователя
                        $client->telegram_id = null;
                        $client->telegram_username = null;
                        $client->is_verified = false;
                        $client->save();
                        
                        session()->flash('warning', 'Авторизация Telegram была отозвана. Статус верификации сброшен.');
                        break;
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Error checking Telegram authorization: ' . $e->getMessage());
            // Не отвязываем при сетевых ошибках - может быть временная проблема
        }
    }
}