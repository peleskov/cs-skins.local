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

    // ================== ОСНОВНЫЕ МЕТОДЫ ПРОФИЛЯ ==================

    /**
     * Страница профиля пользователя
     */
    public function index(): View
    {
        $client = $this->getAuthenticatedClient();
        $telegramBotName = env('TELEGRAM_BOT_NAME');
        
        
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
     * Получить транзакции клиента
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        $perPage = min($request->get('per_page', 20), 100); // Максимум 100 на страницу

        $transactions = $client->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $transactions->getCollection()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at->toISOString(),
                'metadata' => $transaction->metadata,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
                'has_more_pages' => $transactions->hasMorePages(),
            ]
        ]);
    }

    /**
     * Получить статистику продаж клиента
     */
    public function getSalesStats(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        // Получаем статистику из завершенных транзакций продаж
        $salesTransactions = $client->transactions()
            ->where('type', \App\Models\Transaction::TYPE_SALE)
            ->where('status', \App\Models\Transaction::STATUS_COMPLETED)
            ->get();

        $stats = [
            'total_earned' => $salesTransactions->sum('amount'),
            'total_sales' => $salesTransactions->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
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
     * Генерация кода верификации для Telegram
     */
    public function generateTelegramVerificationCode(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        // Проверяем, что Telegram еще не подключен
        if ($client->telegram_id && $client->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram уже подключен'
            ], 400);
        }

        // Генерируем код
        $code = $client->generateTelegramVerificationCode();

        // Получаем имя бота из конфига
        $botName = config('services.telegram.bot_name');

        return response()->json([
            'success' => true,
            'code' => $code,
            'bot_url' => "https://t.me/{$botName}?start={$code}",
            'expires_in' => 600 // 10 минут в секундах
        ]);
    }







    // ================== NOTIFICATION SETTINGS МЕТОДЫ ==================

    /**
     * Обновление настроек уведомлений
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        $request->validate([
            'notification_settings' => 'array',
            'notification_settings.*' => 'in:email,telegram'
        ]);

        $notificationSettings = $request->notification_settings ?? [];

        // Проверяем, что пользователь может включить email уведомления
        if (in_array('email', $notificationSettings)) {
            if (!$client->email || !$client->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Для включения email уведомлений необходимо подтвердить email адрес'
                ], 400);
            }
        }

        // Проверяем, что пользователь может включить Telegram уведомления
        if (in_array('telegram', $notificationSettings)) {
            if (!$client->telegram_id || !$client->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Для включения Telegram уведомлений необходимо пройти верификацию через Telegram'
                ], 400);
            }
        }

        $client->notification_settings = $notificationSettings;
        $client->save();

        return response()->json([
            'success' => true,
            'message' => 'Настройки уведомлений обновлены'
        ]);
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

    // ================== BALANCE & TRANSACTIONS МЕТОДЫ ==================

    /**
     * Получить информацию о текущем пользователе
     */
    public function getCurrentUser(): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'steam_id' => $client->steam_id,
            'telegram_id' => $client->telegram_id,
            'telegram_username' => $client->telegram_username,
            'is_verified' => $client->is_verified,
            'email_verified_at' => $client->email_verified_at
        ]);
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
     * Получить данные продаж для пользователя (заказы где пользователь является продавцом)
     */
    private function getSalesData(Client $client, string $activeTab): array
    {
        // Получаем заказы где пользователь является продавцом
        $allOrders = Order::with(['buyer:id,name,steam_id', 'listings', 'tradeOffer.statusHistory'])
            ->where('seller_id', $client->id)
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
     * Получить данные покупок для пользователя (покупки отображаются по заказам)
     */
    private function getPurchasesData(Client $client, string $activeTab): array
    {
        // Получаем заказы где пользователь является покупателем
        $allOrders = Order::with(['seller:id,name,steam_id', 'tradeOffer.statusHistory'])
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
     * Генерация канала на основе seller_id и токена
     */
    private function generateChannel(int $sellerId, string $token): string
    {
        $hash = substr(hash('sha256', $sellerId . $token), 0, 16);
        return "seller-{$sellerId}-{$hash}";
    }
}