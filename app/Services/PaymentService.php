<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Transaction;
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
    public function createPaymentForm(Client $client, float $amount, ?string $successUrl = null, ?string $failUrl = null): array
    {
        try {
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
     * Check if payment type is enabled
     */
    private function isPaymentTypeEnabled(string $paymentType): bool
    {
        $settingKey = match ($paymentType) {
            'card' => 'card_payment_enabled',
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
     * Complete purchase and credit client balance
     */
    private function completePurchase(Payment $payment): void
    {
        if ($payment->isPaid()) {
            return; // Already processed
        }

        DB::transaction(function () use ($payment) {
            // Credit client balance
            $payment->client->credit($payment->amount);

            // Create transaction record
            Transaction::create([
                'client_id' => $payment->client_id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $payment->amount,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Пополнение баланса картой на сумму " . number_format($payment->amount, 0, ',', ' ') . " ₽",
                'metadata' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'merchant_order_id' => $payment->merchant_order_id,
                    'payment_method' => 'card',
                ],
            ]);

            // Mark payment as paid
            $payment->markAsPaid();

            /*

            Log::info('Balance deposit completed', [
                'payment_id' => $payment->id,
                'client_id' => $payment->client_id,
                'amount' => $payment->amount,
            ]);
            */
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
}
