<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Exception;
use App\Events\ExtensionEvents;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Notifications\VerifyEmailNotification;
use App\Models\BalanceWithdrawRequest;
use App\Models\Client;
use App\Models\Order;
use App\Models\SiteSetting;

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
        $telegramBotName = config('services.telegram.bot_name');

        // Делаем extension_token видимым для владельца профиля
        $client->makeVisible(['extension_token']);
        $client->setAttribute('is_premium', $client->isPremium());
        $client->setAttribute('premium_expires_at', optional($client->subscription)->expires_at);

        // Переводы для табов профиля
        $profileTabs = __('profile.tabs');

        // Настройки депозитов
        $depositSettings = [
            'minimum_amount' => \App\Models\SiteSetting::get('minimum_deposit_amount', 100),
            'maximum_amount' => \App\Models\SiteSetting::get('maximum_deposit_amount', 50000),
            'card_payment_enabled' => \App\Models\SiteSetting::get('card_payment_enabled', true),
            'test_payment_enabled' => \App\Models\SiteSetting::get('test_payment_enabled', false),
        ];

        $withdrawSettings = [
            'minimum_amount' => (float) \App\Models\SiteSetting::get('minimum_withdraw_amount', 100),
        ];

        return view('profile.index', compact('client', 'telegramBotName', 'profileTabs', 'depositSettings', 'withdrawSettings'));
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
        $validation = Client::validateTradeUrl($tradeUrl, $client->steam_id);
        
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

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [25, 50, 100])) $perPage = 25;

        // Получаем ID заказов, которые сейчас на холде
        $heldOrderIds = Order::where(function ($q) use ($client) {
                $q->where('seller_id', $client->id)
                  ->orWhere('buyer_id', $client->id);
            })
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '>', now());
            })
            ->pluck('id');

        $transactions = $client->transactions()
            ->with('order')
            ->where(function ($q) use ($heldOrderIds) {
                $q->whereNull('order_id')
                  ->orWhereNotIn('order_id', $heldOrderIds);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $transactions->getCollection()->map(function ($transaction) {
            // Получаем listing_id из cart_snapshot заказа
            $listingId = null;
            if ($transaction->order && $transaction->order->cart_snapshot) {
                $firstItem = $transaction->order->cart_snapshot[0] ?? null;
                $listingId = $firstItem['listing_id'] ?? null;
            }

            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at->toISOString(),
                'metadata' => $transaction->metadata,
                'listing_id' => $listingId,
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
     * Получить историю бонусных транзакций
     */
    public function getBonusTransactions(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [25, 50, 100])) $perPage = 25;

        $transactions = $client->bonusTransactions()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $transactions->getCollection()->map(function ($tx) {
            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'description' => $tx->description,
                'created_at' => $tx->created_at->toISOString(),
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
            ->where('type', Transaction::TYPE_SALE)
            ->where('status', Transaction::STATUS_COMPLETED)
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
     * Получить информацию о средствах в холде
     */
    public function getHeldBalance(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        return response()->json([
            'success' => true,
            'data' => [
                'seller_held_balance' => $client->getSellerHeldBalance(),
                'buyer_held_balance' => $client->getBuyerHeldBalance(),
                'seller_held_count' => $client->getSellerHeldOrders()->count(),
                'buyer_held_count' => $client->getBuyerHeldOrders()->count(),
            ],
        ]);
    }

    /**
     * Получить заказы на удержании (продажи + покупки) с пагинацией
     */
    public function getHeldOrders(Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [25, 50, 100])) $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));

        $sellerOrders = $client->getSellerHeldOrders()->map(function ($order) {
            return [
                'id' => $order->id,
                'kind' => 'seller',
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'counterparty_name' => $order->buyer?->name,
                'created_at' => $order->created_at?->toISOString(),
                'settlement_date' => $order->tradeOffer?->settlement_date?->toISOString(),
            ];
        });

        $buyerOrders = $client->getBuyerHeldOrders()->map(function ($order) {
            return [
                'id' => $order->id,
                'kind' => 'buyer',
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'counterparty_name' => $order->seller?->name,
                'created_at' => $order->created_at?->toISOString(),
                'settlement_date' => $order->tradeOffer?->settlement_date?->toISOString(),
            ];
        });

        $merged = $sellerOrders->concat($buyerOrders)
            ->sortBy('settlement_date')
            ->values();

        $total = $merged->count();
        $items = $merged->forPage($page, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
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
            'notification_settings.*' => 'in:email,telegram,toast'
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

        } catch (Exception $e) {
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
                    broadcast(ExtensionEvents::forceLogout($oldChannel, 'Токен изменен. Требуется переавторизация.'));
                    Log::info('Force logout sent to old channel', [
                        'client_id' => $client->id,
                        'old_channel' => $oldChannel
                    ]);
                } catch (Exception $e) {
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

        } catch (Exception $e) {
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
            'email_verified_at' => $client->email_verified_at,
            'balance' => (float) $client->balance,
            'bonus_balance' => (float) $client->bonus_balance,
            'withdraw_blocked_until' => $client->withdraw_blocked_until,
            'withdraw_block_reason_user' => $client->withdraw_block_reason_user,
            'purchases_blocked_until' => $client->purchases_blocked_until,
            'purchases_block_reason_user' => $client->purchases_block_reason_user,
            'balance_blocked_until' => $client->balance_blocked_until,
            'balance_block_reason_user' => $client->balance_block_reason_user,
        ]);
    }

    /**
     * Создать заявку на вывод баланса (6.7).
     * Деньги списываются сразу (заморожены), при reject вернутся на баланс.
     */
    public function withdrawBalance(\Illuminate\Http\Request $request): JsonResponse
    {
        $client = $this->getAuthenticatedClient();

        // Блокировки
        if ($client->isWithdrawBlocked()) {
            return response()->json([
                'success' => false,
                'message' => $client->getWithdrawBlockReasonForUser() ?: 'Вывод заблокирован администратором',
            ], 403);
        }

        $minAmount = (float) SiteSetting::get('minimum_withdraw_amount', 100);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minAmount],
        ], [
            'amount.required' => 'Укажите сумму',
            'amount.numeric' => 'Сумма должна быть числом',
            'amount.min' => "Минимальная сумма вывода: {$minAmount} ₽",
        ]);

        $amount = (float) $request->input('amount');

        if ($amount > (float) $client->balance) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно средств на балансе',
            ], 400);
        }

        // Считаем суммы заявок (pending + approved) за 24ч и 1ч
        $base = BalanceWithdrawRequest::whereIn('status', [
            BalanceWithdrawRequest::STATUS_PENDING,
            BalanceWithdrawRequest::STATUS_APPROVED,
        ]);

        $user24h = (float) (clone $base)->where('client_id', $client->id)
            ->where('created_at', '>=', now()->subDay())->sum('amount');
        $total24h = (float) (clone $base)->where('created_at', '>=', now()->subDay())->sum('amount');
        $total1h = (float) (clone $base)->where('created_at', '>=', now()->subHour())->sum('amount');

        $limitDailyTotal = (float) SiteSetting::get('withdraw_limit_daily_total', 0);
        $limitDailyPerUser = (float) SiteSetting::get('withdraw_limit_daily_per_user', 0);
        $limitHourlyTotal = (float) SiteSetting::get('withdraw_limit_hourly_total', 0);

        $exceeded = false;
        if ($limitDailyPerUser > 0 && ($user24h + $amount) > $limitDailyPerUser) {
            $exceeded = true;
        }
        if ($limitDailyTotal > 0 && ($total24h + $amount) > $limitDailyTotal) {
            $exceeded = true;
        }
        if ($limitHourlyTotal > 0 && ($total1h + $amount) > $limitHourlyTotal) {
            $exceeded = true;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($client, $amount, $user24h, $total1h, $exceeded) {
            // Замораживаем сумму на балансе
            $client->decrement('balance', $amount);

            $req = BalanceWithdrawRequest::create([
                'client_id' => $client->id,
                'amount' => $amount,
                'withdrawn_24h_snapshot' => $user24h,
                'withdrawn_1h_snapshot' => $total1h,
                'limit_exceeded' => $exceeded,
            ]);

            // Запись в истории операций (Transaction.history) — статус pending
            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'status' => 'pending',
                'description' => 'Заявка на вывод #'.$req->id,
                'metadata' => ['balance_withdraw_request_id' => $req->id],
            ]);
        });

        $message = $exceeded
            ? 'Вывод сейчас временно прекращён, ваша заявка отправлена администраторам. Как только она будет одобрена, баланс будет выведен.'
            : 'Заявка на вывод создана. Средства зарезервированы и будут выведены после одобрения администратором.';

        return response()->json([
            'success' => true,
            'limit_exceeded' => $exceeded,
            'message' => $message,
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