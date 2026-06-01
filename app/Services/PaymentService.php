<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Promocode;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Services\LosReferidosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    private string $baseUrl;
    private string $apiKey;
    private string $username;
    private string $password;
    private string $publicKey;
    private ?string $bearerToken;

    public function __construct()
    {
        $this->baseUrl = config('payment.base_url');
        $this->apiKey = config('payment.api_key');
        $this->username = config('payment.username');
        $this->password = config('payment.password');
        $this->publicKey = config('payment.public_key');
        $this->bearerToken = config('payment.bearer_token');
    }

    /**
     * Create payment form for balance deposit using ArcopayAPI
     * This method is PCI DSS compliant as card data is handled by the acquiring system
     */
    public function createPaymentForm(Client $client, float $amount, ?string $successUrl = null, ?string $failUrl = null, ?int $promocodeId = null, string $paymentType = 'card'): array
    {
        try {
            // Handle test payment type (only in non-production)
            if ($paymentType === 'test') {
                return $this->createTestPayment($client, $amount, $promocodeId);
            }

            // СБП — отдельный поток (QR + поллинг)
            if ($paymentType === 'sbp') {
                return $this->createSbpDeposit($client, $amount, $promocodeId);
            }

            // Check if card payment is enabled
            if (!$this->isPaymentTypeEnabled('card')) {
                return [
                    'success' => false,
                    'message' => 'Пополнение баланса картой временно недоступно'
                ];
            }

            // Validate amount limits
            $limits = $this->getLimitDepositAmount();

            if ($amount < $limits['min']) {
                return [
                    'success' => false,
                    'message' => "Минимальная сумма пополнения: {$limits['min']} руб."
                ];
            }

            if ($amount > $limits['max']) {
                return [
                    'success' => false,
                    'message' => "Максимальная сумма пополнения: {$limits['max']} руб."
                ];
            }

            // Generate our internal order_id
            $orderId = Payment::generateOrderId();

            $data = [
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => config('payment.default_currency', 'RUB'),
                'SuccessUrl' => $successUrl,
                'FailUrl' => $failUrl,
            ];

            // Create order in acquiring system with payment form support
            $acquiringResult = $this->createAcquiringOrderIsForm($data);

            if (!$acquiringResult['success']) {
                return $acquiringResult;
            }

            // Create payment record after successful acquiring order creation
            $payment = Payment::create([
                'client_id' => $client->id,
                'order_id' => $orderId,
                'merchant_order_id' => $acquiringResult['OrderId'],
                'amount' => $amount,
                'currency' => config('payment.default_currency', 'RUB'),
                'status' => Payment::STATUS_CREATED,
                'payment_type' => Payment::TYPE_CARD,
                'promocode_id' => $promocodeId,
                'expires_at' => now()->addMinutes(config('payment.form_lifetime_minutes', 30)),
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_form_url' => $acquiringResult['payment_form_url'],
                'order_id' => $payment->merchant_order_id,
                'amount' => $payment->amount,
                'expires_at' => $payment->expires_at,
            ];
        } catch (Exception $e) {
            Log::error('Payment Form creation failed', [
                'client_id' => $client->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка создания платежа. Попробуйте позже.'
            ];
        }
    }

    /**
     * Создать платёж за PREMIUM-подписку
     */
    public function createSubscriptionPayment(Client $client, SubscriptionPlan $plan): array
    {
        try {
            // Проверка триала
            if ($plan->is_trial && $client->trial_used) {
                return [
                    'success' => false,
                    'message' => 'Триал уже использован',
                ];
            }

            // Тестовый платёж — сразу завершаем без эквайринга
            if (\App\Models\SiteSetting::get('test_payment_enabled', false)) {
                return $this->createTestSubscriptionPayment($client, $plan);
            }

            if (!$this->isPaymentTypeEnabled('card')) {
                return [
                    'success' => false,
                    'message' => 'Оплата картой временно недоступна',
                ];
            }

            $orderId = Payment::generateOrderId();

            $data = [
                'order_id' => $orderId,
                'amount' => (float) $plan->price,
                'currency' => config('payment.default_currency', 'RUB'),
            ];

            $acquiringResult = $this->createAcquiringOrderIsForm($data);

            if (!$acquiringResult['success']) {
                return $acquiringResult;
            }

            $payment = Payment::create([
                'client_id' => $client->id,
                'order_id' => $orderId,
                'merchant_order_id' => $acquiringResult['OrderId'],
                'amount' => $plan->price,
                'currency' => config('payment.default_currency', 'RUB'),
                'status' => Payment::STATUS_CREATED,
                'payment_type' => Payment::TYPE_CARD,
                'payable_type' => SubscriptionPlan::class,
                'payable_id' => $plan->id,
                'expires_at' => now()->addMinutes(config('payment.form_lifetime_minutes', 30)),
            ]);

            Log::info('Платёж за подписку создан', [
                'client_id' => $client->id,
                'plan' => $plan->name,
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_form_url' => $acquiringResult['payment_form_url'],
                'order_id' => $payment->merchant_order_id,
                'amount' => $payment->amount,
                'expires_at' => $payment->expires_at,
            ];
        } catch (Exception $e) {
            Log::error('Ошибка создания платежа за подписку', [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка создания платежа. Попробуйте позже.',
            ];
        }
    }

    /**
     * Тестовый платёж за подписку — сразу завершается без эквайринга
     */
    private function createTestSubscriptionPayment(Client $client, SubscriptionPlan $plan): array
    {
        try {
            $orderId = Payment::generateOrderId();

            $payment = Payment::create([
                'client_id' => $client->id,
                'order_id' => $orderId,
                'merchant_order_id' => 'TEST_' . $orderId,
                'amount' => $plan->price,
                'currency' => config('payment.default_currency', 'RUB'),
                'status' => Payment::STATUS_CREATED,
                'payment_type' => Payment::TYPE_TEST,
                'payable_type' => SubscriptionPlan::class,
                'payable_id' => $plan->id,
            ]);

            $this->completePurchase($payment);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'test_payment' => true,
                'message' => 'Тестовый платеж за подписку выполнен',
            ];
        } catch (Exception $e) {
            Log::error('Test subscription payment failed', [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка тестового платежа: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create test payment - immediately completes without external acquiring
     */
    private function createTestPayment(Client $client, float $amount, ?int $promocodeId = null): array
    {
        // Check if test payment is enabled in settings
        if (!\App\Models\SiteSetting::get('test_payment_enabled', false)) {
            return [
                'success' => false,
                'message' => 'Тестовые платежи недоступны'
            ];
        }

        try {
            $orderId = Payment::generateOrderId();

            // Create payment record
            $payment = Payment::create([
                'client_id' => $client->id,
                'order_id' => $orderId,
                'merchant_order_id' => 'TEST_' . $orderId,
                'amount' => $amount,
                'currency' => config('payment.default_currency', 'RUB'),
                'status' => Payment::STATUS_CREATED,
                'payment_type' => Payment::TYPE_TEST,
                'promocode_id' => $promocodeId,
            ]);

            // Immediately complete the payment
            $this->completePurchase($payment);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'status' => 'completed',
                'message' => 'Тестовый платеж успешно выполнен',
            ];
        } catch (Exception $e) {
            Log::error('Test payment failed', [
                'client_id' => $client->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка тестового платежа: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create order in acquiring system for payment forms
     */
    private function createAcquiringOrderIsForm($data): array
    {
        /*
        Log::info('=== Starting createAcquiringOrderIsForm ===', [
            'order_id' => $data['order_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency']
        ]);
        */
        
        try {
            // Support all payment types (empty array = all available)
            $paymentTypes = [];

            $requestData = [
                'MerchantOrderId' => $data['order_id'],
                'Currency' => $data['currency'],
                'Amount' => (int) round($data['amount'] * 100), // Конвертируем рубли в копейки
                'Type' => 'PayIn',
                'PaymentTypes' => $paymentTypes,
                'CallbackUrl' => route('webhook.payment'),
                'LifeTime' => config('payment.form_lifetime_minutes', 30) * 60, // время жизни формы в секундах
                'IsForm' => true,
            ];
            
            // Add redirect URLs if provided according to ArcoPayAPI documentation
            if (isset($data['SuccessUrl']) && !empty($data['SuccessUrl']) ||
                isset($data['FailUrl']) && !empty($data['FailUrl'])) {
                $requestData['RedirectUrl'] = [];

                if (isset($data['SuccessUrl']) && !empty($data['SuccessUrl'])) {
                    $requestData['RedirectUrl']['SuccessUrl'] = $data['SuccessUrl'];
                }
                if (isset($data['FailUrl']) && !empty($data['FailUrl'])) {
                    $requestData['RedirectUrl']['FailUrl'] = $data['FailUrl'];
                }
            }

            // Use Bearer Token for payment form requests (required when IsForm: true)
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
            ];

            $httpRequest = Http::timeout(30);
            $httpRequest = $httpRequest->withToken($this->bearerToken);

            $response = $httpRequest->withHeaders($headers)
                ->post($this->baseUrl . '/payments/create', $requestData);

            if ($response->failed()) {
                Log::error('Acquiring Order Creation Failed', [
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'request_data' => $requestData
                ]);
                return [
                    'success' => false,
                    'message' => 'Ошибка создания заказа: ' . $response->body()
                ];
            }

            $data = $response->json();

            /*
            Log::info('Acquiring response received', [
                'full_response' => $data,
                'response_structure' => array_keys($data ?? []),
                'order_structure' => array_keys($data['Order'] ?? []),
            ]);
            */

            // Проверяем успешность операции
            if (!($data['Response']['Success'] ?? false)) {
                $errorCode = $data['Response']['ErrCode'] ?? 'UNKNOWN_ERROR';
                $errorMessage = $data['Response']['ErrMessage'] ?? 'Неизвестная ошибка эквайринга';

                Log::error('Acquiring returned error', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'full_response' => $data
                ]);

                return [
                    'success' => false,
                    'message' => "Ошибка эквайринга: {$errorMessage}"
                ];
            }

            $merchantOrderId = $data['Order']['OrderId'] ?? null;

            if (!$merchantOrderId) {
                Log::error('Не получен OrderId от эквайринга', [
                    'response_data' => $data,
                    'order_id' => $data['order_id'],
                ]);
                return [
                    'success' => false,
                    'message' => 'Не получен OrderId от эквайринга'
                ];
            }

            // Generate payment form URL according to ArcoPayAPI documentation
            // Format: https://payment.arcopay.tech/{OrderID}
            $paymentFormUrl = 'https://payment.arcopay.tech/' . $merchantOrderId;

            return [
                'success' => true,
                'OrderId' => $merchantOrderId, // Номер заказа от эквайринга
                'payment_form_url' => $paymentFormUrl
            ];
        } catch (Exception $e) {
            Log::error('Exception during acquiring order creation for form', [
                'order_id' => $data['order_id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка соединения с платежной системой'
            ];
        }
    }

    /**
     * Создать SBP-платёж на пополнение баланса
     */
    private function createSbpDeposit(Client $client, float $amount, ?int $promocodeId = null): array
    {
        try {
            if (!$this->isPaymentTypeEnabled('sbp')) {
                return [
                    'success' => false,
                    'message' => 'Пополнение баланса через СБП временно недоступно',
                ];
            }

            $limits = $this->getLimitDepositAmount();
            if ($amount < $limits['min']) {
                return ['success' => false, 'message' => "Минимальная сумма пополнения: {$limits['min']} руб."];
            }
            if ($amount > $limits['max']) {
                return ['success' => false, 'message' => "Максимальная сумма пополнения: {$limits['max']} руб."];
            }

            $orderId = Payment::generateOrderId();
            $lifetimeMinutes = (int) config('payment.form_lifetime_minutes', 30);

            // Создаём Payment ДО запросов к эквайрингу, иначе ранние callback'и
            // (CREATED / QRCDATA_CREATED) прилетают раньше, чем мы успеем записать в БД.
            $payment = Payment::create([
                'client_id' => $client->id,
                'order_id' => $orderId,
                'merchant_order_id' => $orderId,
                'amount' => $amount,
                'currency' => config('payment.default_currency', 'RUB'),
                'status' => Payment::STATUS_CREATED,
                'payment_type' => Payment::TYPE_SBP,
                'promocode_id' => $promocodeId,
                'expires_at' => now()->addMinutes($lifetimeMinutes),
            ]);

            $http = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ]);

            $createResponse = $http->post($this->baseUrl . '/payments/create', [
                'MerchantOrderId' => $orderId,
                'Currency' => config('payment.default_currency', 'RUB'),
                'Amount' => (int) round($amount * 100),
                'Type' => 'PayIn',
                'PaymentTypes' => ['IPS'],
                'CallbackUrl' => route('webhook.payment'),
                'LifeTime' => $lifetimeMinutes * 60,
                'IsForm' => false,
            ]);

            if ($createResponse->failed()) {
                Log::error('SBP order creation failed', [
                    'order_id' => $orderId,
                    'status' => $createResponse->status(),
                    'body' => $createResponse->body(),
                ]);
                $payment->markAsFailed();
                return ['success' => false, 'message' => 'Ошибка создания заказа СБП'];
            }

            $createData = $createResponse->json();
            if (!($createData['Response']['Success'] ?? false)) {
                Log::error('SBP acquiring returned error', ['response' => $createData]);
                $payment->markAsFailed();
                return ['success' => false, 'message' => 'Не удалось создать платёж СБП. Попробуйте позже.'];
            }

            $acquiringOrderId = $createData['Order']['OrderId'] ?? null;
            if (!$acquiringOrderId) {
                Log::error('SBP: no OrderId in response', ['response' => $createData]);
                $payment->markAsFailed();
                return ['success' => false, 'message' => 'Не получен OrderId от эквайринга'];
            }

            $qrResponse = $http->post($this->baseUrl . '/payments/ips/qrcData', [
                'QrcType' => '02',
                'TemplateVersion' => '01',
                'QrTtl' => (string) $lifetimeMinutes,
                'OrderId' => $acquiringOrderId,
            ]);

            if ($qrResponse->failed()) {
                Log::error('SBP qrcData failed', [
                    'order_id' => $orderId,
                    'acquiring_order_id' => $acquiringOrderId,
                    'status' => $qrResponse->status(),
                    'body' => $qrResponse->body(),
                ]);
                $payment->markAsFailed();
                return ['success' => false, 'message' => 'Ошибка получения QR-кода'];
            }

            $qrData = $qrResponse->json();
            if (!($qrData['Response']['Success'] ?? true)) {
                Log::error('SBP qrcData error', ['response' => $qrData]);
                $payment->markAsFailed();
                return ['success' => false, 'message' => 'Не удалось получить QR-код. Попробуйте позже.'];
            }

            $qrPayload = $qrData['Qrc']['Payload'] ?? null;

            $payment->update(['merchant_order_id' => $acquiringOrderId]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'order_id' => $acquiringOrderId,
                'amount' => $payment->amount,
                'qr_payload' => $qrPayload,
                'expires_at' => $payment->expires_at,
            ];
        } catch (Exception $e) {
            Log::error('SBP deposit creation exception', [
                'client_id' => $client->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            if (isset($payment) && $payment->exists && !$payment->isPaid()) {
                $payment->markAsFailed();
            }
            return ['success' => false, 'message' => 'Ошибка создания платежа СБП'];
        }
    }

    /**
     * Проверить статус SBP-платежа в эквайринге
     */
    private function checkSbpStatus(Payment $payment): bool
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])->post($this->baseUrl . '/payments/get', [
                    'OrderId' => $payment->merchant_order_id,
                ]);

            if (!$response->successful()) {
                Log::error('SBP status check failed', [
                    'payment_id' => $payment->id,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $status = $response->json('Order.Status');

            if (in_array($status, ['CHARGED', 'IPS_ACCEPTED'])) {
                $this->completePurchase($payment);
                return true;
            } elseif (in_array($status, ['DECLINED', 'EXPIRED', 'CHARGE_DECLINED'])) {
                $payment->markAsFailed();
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('SBP status check exception', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if payment type is enabled
     */
    private function isPaymentTypeEnabled(string $paymentType): bool
    {
        $settingKey = match ($paymentType) {
            'card' => 'card_payment_enabled',
            'sbp' => 'sbp_payment_enabled',
            default => null,
        };

        if ($settingKey === null) {
            return false; // Unknown payment type is disabled by default
        }

        return \App\Models\SiteSetting::get($settingKey, true);
    }

    /**
     * Get limit deposit amount from settings
     */
    private function getLimitDepositAmount(): array
    {
        return [
            'min' => (float) \App\Models\SiteSetting::get('minimum_deposit_amount', 100),
            'max' => (float) \App\Models\SiteSetting::get('maximum_deposit_amount', 50000),
        ];
    }



    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->publicKey)) {
            Log::warning('Webhook signature verification skipped: public key not configured', [
                'payload_length' => strlen($payload),
                'signature_prefix' => substr($signature, 0, 20) . '...'
            ]);
            return true; // Allow webhooks when key not configured (for testing)
        }

        $signature = base64_decode($signature);

        $result = openssl_verify($payload, $signature, $this->publicKey, OPENSSL_ALGO_SHA1);

        if ($result !== 1) {
            Log::error('Webhook signature verification failed', [
                'openssl_error' => openssl_error_string(),
                'payload_length' => strlen($payload)
            ]);
        }

        return $result === 1;
    }


    /**
     * Process webhook from acquiring system
     */
    public function processWebhook(array $webhookData, string $source = 'webhook'): bool
    {
        try {
            if (!isset($webhookData['Order'])) {
                Log::error('Invalid webhook data: missing Order', ['data' => $webhookData]);
                return false;
            }

            $order = $webhookData['Order'];
            $merchantOrderId = $order['MerchantOrderId'] ?? null;
            $status = $order['Status'] ?? null;

            if (!$merchantOrderId || !$status) {
                Log::error('Invalid webhook data: missing required fields', ['order' => $order]);
                return false;
            }

            $payment = Payment::where('order_id', $merchantOrderId)->first();
            if (!$payment) {
                Log::error('Payment not found for webhook', ['order_id' => $merchantOrderId]);
                return false;
            }

            // Store webhook data
            $payment->update([
                'webhook_data' => array_merge($payment->webhook_data ?? [], [
                    'received_at' => now(),
                    'source' => $source,
                    'data' => $webhookData,
                ])
            ]);

            // Process payment status
            if ($status === 'CHARGED') {
                $this->completePurchase($payment);
                return true;
            } elseif (in_array($status, ['DECLINED', 'EXPIRED', 'CHARGE_DECLINED'])) {
                $payment->markAsFailed();
                return true;
            }

            return true;
        } catch (Exception $e) {
            Log::error('Exception during webhook processing', [
                'webhook_data' => $webhookData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Complete purchase — различаем по payable_type
     */
    private function completePurchase(Payment $payment): void
    {
        if ($payment->isPaid()) {
            return;
        }

        // Платёж за подписку
        if ($payment->payable instanceof SubscriptionPlan) {
            $this->completeSubscriptionPurchase($payment);
            return;
        }

        // Пополнение баланса (как раньше)
        $this->completeDepositPurchase($payment);
    }

    /**
     * Активация подписки после успешной оплаты
     */
    private function completeSubscriptionPurchase(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $subscriptionService = app(SubscriptionService::class);
            $subscription = $subscriptionService->purchase($payment->client, $payment->payable, $payment->id);

            $payment->markAsPaid();

            // Запускаем получение токена подписки через 10 сек
            if (!$subscription->subscription_token) {
                \App\Jobs\FetchSubscriptionToken::dispatch($subscription)
                    ->delay(now()->addSeconds(10));
            }

            // LR-события для подписки: subscription или rebill
            $referral = $payment->client->referral;
            if ($referral && $referral->is_active) {
                $lrService = app(LosReferidosService::class);
                $hadSubscription = $payment->client->subscription
                    && $payment->client->subscription->id !== $subscription->id;

                if ($hadSubscription) {
                    $lrService->sendRebill($referral, $payment);
                } else {
                    $lrService->sendSubscription($referral, $payment);
                }
            }
        });
    }

    /**
     * Зачисление на баланс после успешной оплаты
     */
    private function completeDepositPurchase(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->client->credit($payment->amount);

            $paymentMethodName = match ($payment->payment_type) {
                Payment::TYPE_TEST => 'тестовый платеж',
                Payment::TYPE_SBP => 'СБП',
                default => 'картой',
            };

            Transaction::create([
                'client_id' => $payment->client_id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $payment->amount,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Пополнение баланса ({$paymentMethodName}) на сумму " . number_format($payment->amount, 0, ',', ' ') . " ₽",
                'metadata' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'merchant_order_id' => $payment->merchant_order_id,
                    'payment_method' => $payment->payment_type,
                ],
            ]);

            if ($payment->promocode_id) {
                $promocode = Promocode::find($payment->promocode_id);
                if ($promocode) {
                    $promocodeService = app(PromocodeService::class);
                    $promocodeService->apply($promocode, $payment->client, $payment);
                }
            }

            $payment->markAsPaid();

            // LR-событие: deposit (пополнение баланса)
            $referral = $payment->client->referral;
            if ($referral && $referral->is_active) {
                $lrService = app(LosReferidosService::class);
                $lrService->sendDeposit($referral, $payment);
            }
        });
    }


    /**
     * Check payment status with acquiring system
     */
    public function checkPaymentStatus(Payment $payment): bool
    {
        if (!$payment->order_id || $payment->isPaid()) {
            return false;
        }

        if ($payment->payment_type === Payment::TYPE_SBP) {
            return $this->checkSbpStatus($payment);
        }

        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])->post($this->baseUrl . '/payments/status', [
                    'OrderId' => $payment->merchant_order_id
                ]);

            if (!$response->successful()) {
                Log::error('Failed to check payment status', [
                    'payment_id' => $payment->id,
                    'merchant_order_id' => $payment->merchant_order_id,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $data = $response->json();

            /*
            Log::info('Payment status check response', [
                'payment_id' => $payment->id,
                'merchant_order_id' => $payment->merchant_order_id,
                'response_data' => $data,
            ]);
            */

            $order = $data['Order'] ?? null;
            $status = $order['Status'] ?? null;

            if (!$status) {
                Log::error('No status in payment check response', [
                    'payment_id' => $payment->id,
                    'response_data' => $data,
                ]);
                return false;
            }

            // Complete purchase if payment is successful
            if ($status === 'CHARGED') {
                $this->completePurchase($payment);
                return true;
            } elseif (in_array($status, ['DECLINED', 'EXPIRED', 'CHARGE_DECLINED'])) {
                $payment->markAsFailed();
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Exception during payment status check', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить данные подписки (SubscriptionToken, MemberId) по OrderId
     */
    public function getSubscriptionDetails(string $orderId): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withToken($this->bearerToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])
                ->post($this->baseUrl . '/payments/ips/subscription', [
                    'OrderId' => $orderId,
                ]);

            if ($response->failed()) {
                Log::error('Ошибка получения данных подписки', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $details = $data['SubscriptionDetails'] ?? [];
            $subscriptionToken = $details['SubscriptionToken'] ?? null;
            $memberId = $details['MemberId'] ?? null;

            // Проверяем на placeholder-значения
            $isFakeToken = $subscriptionToken && preg_match('/^0+$/', $subscriptionToken);
            $isFakeMemberId = $memberId == 4294967295;

            if ($isFakeToken || $isFakeMemberId) {
                Log::warning('Получены placeholder данные подписки', [
                    'order_id' => $orderId,
                    'subscription_token' => $subscriptionToken,
                    'member_id' => $memberId,
                ]);
                $subscriptionToken = null;
                $memberId = null;
            }

            Log::info('Данные подписки получены', [
                'order_id' => $orderId,
                'token_received' => !empty($subscriptionToken),
                'member_id_received' => !empty($memberId),
            ]);

            return [
                'subscription_token' => $subscriptionToken,
                'member_id' => $memberId,
            ];
        } catch (Exception $e) {
            Log::error('Ошибка получения данных подписки', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Автоматическое списание для продления подписки по SubscriptionToken
     */
    public function chargeSubscriptionRenewal(\App\Models\Subscription $subscription): Payment
    {
        $client = $subscription->client;
        $plan = $subscription->plan;

        if (!$subscription->subscription_token) {
            throw new Exception('Отсутствует токен подписки для автоматического списания');
        }

        if (!$plan) {
            throw new Exception('Не найден тарифный план подписки');
        }

        $orderId = Payment::generateOrderId();

        $payment = Payment::create([
            'client_id' => $client->id,
            'order_id' => $orderId,
            'amount' => $plan->price,
            'currency' => config('payment.default_currency', 'RUB'),
            'status' => Payment::STATUS_CREATED,
            'payment_type' => Payment::TYPE_CARD,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        try {
            // Шаг 1: Создаём заказ в эквайринге
            $createResponse = Http::timeout(30)
                ->withToken($this->bearerToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])
                ->post($this->baseUrl . '/payments/create', [
                    'MerchantOrderId' => $orderId,
                    'Currency' => config('payment.default_currency', 'RUB'),
                    'Amount' => (int) round($plan->price * 100),
                    'Type' => 'PayIn',
                    'PaymentTypes' => [],
                    'CallbackUrl' => route('webhook.payment'),
                    'LifeTime' => 900,
                    'IsForm' => false,
                ]);

            if ($createResponse->failed()) {
                throw new Exception('Ошибка создания заказа для продления: ' . $createResponse->body());
            }

            $createData = $createResponse->json();
            $acquiringOrderId = $createData['Order']['OrderId'] ?? null;

            if (!$acquiringOrderId) {
                throw new Exception('Не получен OrderId для продления подписки');
            }

            // Шаг 2: Автоматическое списание по токену подписки
            $chargeResponse = Http::timeout(30)
                ->withToken($this->bearerToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])
                ->post($this->baseUrl . '/payments/ips/qrcData', [
                    'OrderId' => $acquiringOrderId,
                    'QrcType' => '02',
                    'TemplateVersion' => '01',
                    'QrTtl' => '15',
                    'Description' => "Автопродление PREMIUM-подписки «{$plan->name}»",
                    'SubscriptionDetails' => [
                        'NeedSubscription' => false,
                        'SubscriptionToken' => $subscription->subscription_token,
                    ],
                ]);

            if ($chargeResponse->failed()) {
                throw new Exception('Ошибка автоматического списания: ' . $chargeResponse->body());
            }

            $payment->update([
                'merchant_order_id' => $acquiringOrderId,
                'status' => Payment::STATUS_CREATED, // Ожидаем webhook
            ]);

            Log::info('Автоматическое списание за подписку инициировано', [
                'subscription_id' => $subscription->id,
                'client_id' => $client->id,
                'payment_id' => $payment->id,
                'order_id' => $acquiringOrderId,
            ]);

            return $payment;
        } catch (Exception $e) {
            Log::error('Ошибка автоматического списания за подписку', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->markAsFailed();
            throw $e;
        }
    }
}
