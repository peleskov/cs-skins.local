<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Notifications\VerifyEmailNotification;
use App\Models\Client;
use App\Models\Order;

class ProfileController extends Controller
{
    // Константы для статусов заказов
    private const ORDER_STATUS_NEW = 'paid';
    private const ORDER_STATUS_PENDING = 'processing';
    private const ORDER_STATUS_COMPLETED = 'completed';
    private const ORDER_STATUSES_CANCELLED = ['cancelled', 'failed', 'refunded'];

    // Константы для Telegram
    private const TELEGRAM_DATA_EXPIRY = 86400; // 24 часа
    private const TELEGRAM_BLOCKED_ERRORS = [
        'bot was blocked by the user',
        'user is deactivated',
        'chat not found',
        'bot can\'t initiate conversation'
    ];
    // ================== ОСНОВНЫЕ МЕТОДЫ ПРОФИЛЯ ==================

    /**
     * Страница профиля пользователя
     */
    public function index(): View
    {
        $client = $this->getAuthenticatedClient();
        $telegramBotName = env('TELEGRAM_BOT_NAME');
        
        // Проверяем статус Telegram авторизации если пользователь верифицирован
        if ($client->is_verified && $client->telegram_id) {
            $this->checkTelegramAuthorization($client);
        }
        
        // Делаем extension_token видимым для владельца профиля
        $client->makeVisible(['extension_token']);
        
        // Переводы для табов профиля
        $profileTabs = __('profile.tabs');
        
        return view('profile.index', compact('client', 'telegramBotName', 'profileTabs'));
    }

    /**
     * Страница продаж пользователя
     */
    public function sales(Request $request): View|JsonResponse
    {
        $client = $this->getAuthenticatedClient();
        $activeTab = $request->get('tab', 'new');
        
        $salesData = $this->getSalesData($client, $activeTab);

        // Если AJAX запрос - возвращаем JSON
        if ($request->wantsJson()) {
            return response()->json($salesData);
        }

        return view('profile.sales', array_merge($salesData, ['client' => $client]));
    }

    /**
     * Страница покупок пользователя
     */
    public function purchases(Request $request): View|JsonResponse
    {
        $client = $this->getAuthenticatedClient();
        $activeTab = $request->get('tab', 'new');
        
        $purchasesData = $this->getPurchasesData($client, $activeTab);

        // Если AJAX запрос - возвращаем JSON
        if ($request->wantsJson()) {
            return response()->json($purchasesData);
        }

        return view('profile.purchases', array_merge($purchasesData, ['client' => $client]));
    }

    // ================== EMAIL МЕТОДЫ ==================

    /**
     * Обновление email адреса
     */
    public function updateEmail(Request $request)
    {
        $client = $this->getAuthenticatedClient();
        
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
            
            if ($request->wantsJson()) {
                if ($isNewEmail) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Email адрес добавлен. Проверьте почту для подтверждения.'
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Email адрес изменен. Проверьте почту для подтверждения.'
                    ]);
                }
            }
            
            if ($isNewEmail) {
                return redirect()->route('profile')->with('success', 'Email адрес добавлен. Проверьте почту для подтверждения.');
            } else {
                return redirect()->route('profile')->with('success', 'Email адрес изменен. Проверьте почту для подтверждения.');
            }
        }
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Email адрес не изменился.'
            ]);
        }
        
        return redirect()->route('profile');
    }

    /**
     * Подтверждение email адреса
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $client = $this->getAuthenticatedClient();
        
        Log::info('Email verification attempt', [
            'user_id' => $client->id,
            'url_id' => $id,
            'url_hash' => $hash,
            'user_email_hash' => sha1($client->email),
            'user_email' => $client->email,
            'has_valid_signature' => $request->hasValidSignature(),
            'request_url' => $request->fullUrl(),
            'app_url' => config('app.url')
        ]);
        
        if (!$request->hasValidSignature()) {
            return redirect()->route('profile')->with('error', 'Ссылка для подтверждения недействительна или устарела.');
        }
        
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

    // ================== TRADE URL МЕТОДЫ ==================

    /**
     * Обновление Trade URL
     */
    public function updateTradeUrl(Request $request): JsonResponse|RedirectResponse
    {
        $client = $this->getAuthenticatedClient();
        
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
    public function resendVerification(Request $request): JsonResponse|RedirectResponse
    {
        $client = $this->getAuthenticatedClient();
        
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

    // ================== TELEGRAM МЕТОДЫ ==================

    /**
     * Верификация через Telegram
     */
    public function verifyTelegram(Request $request): JsonResponse|RedirectResponse
    {
        $client = $this->getAuthenticatedClient();
        
        // Получаем данные от Telegram
        $telegramData = $request->all();
        
        Log::info('Telegram verification attempt:', $telegramData);
        
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
        
        // Валидация данных Telegram
        $validation = $this->validateTelegramData($telegramData, $telegramBotToken);
        
        if (!$validation['valid']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверные данные от Telegram.'
                ], 400);
            }
            return redirect()->route('profile')->with('error', 'Неверные данные от Telegram.');
        }
        
        if ($validation['expired']) {
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
        $client = $this->getAuthenticatedClient();
        
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
        Log::info('Telegram webhook received:', $update);

        // Проверяем отзыв авторизации (when user revokes login widget access)
        if (isset($update['revoked_auth'])) {
            $revokedAuth = $update['revoked_auth'];
            $userId = $revokedAuth['user_id'] ?? null;
            
            if ($userId) {
                Log::info("Login widget authorization revoked for user: {$userId}");
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
            Log::info("Revoking Telegram authorization for user {$client->id}, telegram_id: {$telegramUserId}");
            
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
            Http::post($url, [
                'chat_id' => $userId,
                'text' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message: ' . $e->getMessage());
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
            $response = Http::timeout(30)->get($url, [
                'chat_id' => $client->telegram_id
            ]);

            $data = $response->json();
            

            // Если получили ошибку "Forbidden: bot was blocked by the user" или подобную
            if (!$data['ok']) {
                $errorDescription = $data['description'] ?? '';
                
                // Проверяем на типичные ошибки блокировки/отзыва
                foreach (self::TELEGRAM_BLOCKED_ERRORS as $error) {
                    if (stripos($errorDescription, $error) !== false) {
                        Log::info("Telegram authorization revoked for user {$client->id}: {$errorDescription}");
                        
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
            Log::error('Error checking Telegram authorization: ' . $e->getMessage());
            // Не отвязываем при сетевых ошибках - может быть временная проблема
        }
    }

    // ================== EXTENSION TOKEN МЕТОДЫ ==================

    /**
     * Генерация токена для расширения
     */
    public function generateExtensionToken(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();
            $token = $client->generateExtensionToken();

            return response()->json([
                'success' => true,
                'token' => $token,
                'message' => 'Токен расширения сгенерирован успешно'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating extension token: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка генерации токена'
            ], 500);
        }
    }

    /**
     * Регенерация токена для расширения
     */
    public function regenerateExtensionToken(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();
            
            // Получаем старый токен для отправки force_logout на старый канал
            $oldToken = $client->extension_token;
            
            // Генерируем новый токен
            $newToken = $client->regenerateExtensionToken();
            
            // Если был старый токен, отправляем force_logout на старый канал
            if ($oldToken) {
                $oldChannel = $this->generateChannel($client->id, $oldToken);
                
                try {
                    broadcast(\App\Events\ExtensionEvents::forceLogout($oldChannel, 'Токен изменен. Требуется переавторизация.'));
                    Log::info('Force logout sent to old channel', [
                        'client_id' => $client->id,
                        'old_channel' => $oldChannel
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to send force logout', [
                        'client_id' => $client->id,
                        'old_channel' => $oldChannel,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'message' => 'Токен расширения перегенерирован успешно'
            ]);

        } catch (\Exception $e) {
            Log::error('Error regenerating extension token: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка регенерации токена'
            ], 500);
        }
    }


    // ================== ПРИВАТНЫЕ МЕТОДЫ-ХЕЛПЕРЫ ==================

    /**
     * Получить авторизованного клиента
     */
    private function getAuthenticatedClient(): Client
    {
        /** @var Client|null $client */
        $client = auth('client')->user();
        
        if (!$client instanceof Client) {
            abort(401, 'Unauthorized');
        }
        
        return $client;
    }

    /**
     * Получить данные продаж для пользователя (продажи отображаются по скинам)
     */
    private function getSalesData(Client $client, string $activeTab): array
    {
        // Получаем order_items где пользователь является продавцом
        $allOrderItems = \App\Models\OrderItem::with(['order.buyer:id,name,steam_id', 'listing:id,price'])
            ->where('seller_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Группируем order_items по статусам (соответствие статусов order_items и заказов)
        $orderItemsByStatus = [
            'new' => $allOrderItems->where('status', \App\Models\OrderItem::STATUS_RESERVED),
            'pending' => $allOrderItems->where('status', \App\Models\OrderItem::STATUS_TRADE_SENT),
            'completed' => $allOrderItems->where('status', \App\Models\OrderItem::STATUS_COMPLETED),
            'cancelled' => $allOrderItems->where('status', \App\Models\OrderItem::STATUS_CANCELLED)
        ];

        // Считаем количество в каждой группе
        $counts = [
            'new' => $orderItemsByStatus['new']->count(),
            'pending' => $orderItemsByStatus['pending']->count(),
            'completed' => $orderItemsByStatus['completed']->count(),
            'cancelled' => $orderItemsByStatus['cancelled']->count()
        ];

        $currentOrderItems = $orderItemsByStatus[$activeTab] ?? collect();

        return [
            'order_items' => $currentOrderItems->values(),
            'counts' => $counts,
            'activeTab' => $activeTab
        ];
    }

    /**
     * Получить данные покупок для пользователя (покупки отображаются по заказам)
     */
    private function getPurchasesData(Client $client, string $activeTab): array
    {
        // Получаем заказы где пользователь является покупателем
        $allOrders = Order::with(['items' => function($query) {
                // Загружаем только нужные данные из order_items
                $query->select('id', 'order_id', 'listing_id', 'item_name', 'item_image_url', 'price', 'status', 'seller_name');
            }])
            ->where('buyer_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Группируем заказы по статусам
        $ordersByStatus = [
            'new' => $allOrders->where('status', self::ORDER_STATUS_NEW),
            'pending' => $allOrders->where('status', self::ORDER_STATUS_PENDING),
            'completed' => $allOrders->where('status', self::ORDER_STATUS_COMPLETED),
            'cancelled' => $allOrders->filter(function($order) {
                return in_array($order->status, self::ORDER_STATUSES_CANCELLED);
            })
        ];

        // Считаем количество в каждой группе
        $counts = [
            'new' => $ordersByStatus['new']->count(),
            'pending' => $ordersByStatus['pending']->count(),
            'completed' => $ordersByStatus['completed']->count(),
            'cancelled' => $ordersByStatus['cancelled']->count()
        ];

        $currentOrders = $ordersByStatus[$activeTab] ?? collect();

        return [
            'orders' => $currentOrders->values(),
            'counts' => $counts,
            'activeTab' => $activeTab
        ];
    }

    /**
     * Валидировать данные Telegram
     */
    private function validateTelegramData(array $telegramData, string $botToken): array
    {
        $checkHash = $telegramData['hash'];
        unset($telegramData['hash']);
        
        $dataCheckArr = [];
        foreach ($telegramData as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);
        
        $secretKey = hash('sha256', $botToken, true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        
        $isValid = strcmp($calculatedHash, $checkHash) === 0;
        $timeDiff = time() - (int)$telegramData['auth_date'];
        $isExpired = $timeDiff > self::TELEGRAM_DATA_EXPIRY;
        
        return [
            'valid' => $isValid,
            'expired' => $isExpired,
            'time_diff' => $timeDiff
        ];
    }

    /**
     * Генерация канала на основе seller_id и токена
     */
    private function generateChannel(int $sellerId, string $token): string
    {
        $hash = substr(hash('sha256', $sellerId . $token), 0, 16);
        return "seller-{$sellerId}-{$hash}";
    }
}