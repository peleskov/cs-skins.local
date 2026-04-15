<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PartnerController extends Controller
{
    /**
     * API создания партнёра (LR → мы)
     * POST /api/partners
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'secret' => 'required|string',
        ]);

        if ($validated['secret'] !== config('services.partner_api.secret')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $partner = Partner::firstOrCreate(
            ['email' => $validated['email']],
        );

        return response()->json(['partner_id' => $partner->id]);
    }

    /**
     * Отображение лендинга
     * GET /l/{slug}
     */
    public function landing(string $slug)
    {
        $view = "landings.{$slug}";

        if (!view()->exists($view)) {
            abort(404);
        }

        return view($view);
    }

    /**
     * Покупка подписки на лендинге (сценарий Б)
     * POST /api/partner/purchase
     */
    public function purchase(Request $request): JsonResponse
    {
        // Авторизован → не нужен temp-клиент
        if (Auth::guard('client')->check()) {
            return response()->json(['success' => false, 'message' => 'Уже авторизован'], 400);
        }

        // Проверяем сессию — если уже есть платёж
        $sessionClientId = session('partner_client_id');
        $sessionPaymentId = session('partner_payment_id');

        if ($sessionClientId && $sessionPaymentId) {
            $existingPayment = Payment::where('id', $sessionPaymentId)
                ->where('client_id', $sessionClientId)
                ->first();

            if ($existingPayment) {
                if ($existingPayment->isPaid()) {
                    return response()->json([
                        'success' => true,
                        'is_paid' => true,
                        'redirect' => route('auth.steam'),
                    ]);
                }

                if ($existingPayment->status !== 'failed' && $existingPayment->expires_at?->isFuture()) {
                    $qrUrl = $this->getQrUrl($existingPayment->merchant_order_id);
                    if ($qrUrl) {
                        return response()->json([
                            'success' => true,
                            'payment_url' => $qrUrl,
                            'payment_id' => $existingPayment->id,
                        ]);
                    }
                    $existingPayment->markAsFailed();
                }
            }
        }

        // Создаём временного клиента
        $client = Client::create(['name' => 'Partner Referral']);

        // Берём первый активный тариф
        $plan = SubscriptionPlan::active()->orderBy('sort_order')->first();

        if (!$plan) {
            Log::error('Нет активных тарифов для партнёрской покупки');
            return response()->json(['success' => false, 'message' => 'Нет доступных тарифов'], 500);
        }

        $merchantOrderId = Payment::generateOrderId();

        try {
            $result = $this->createSbpPayment($client, $plan, $merchantOrderId);
        } catch (\Exception $e) {
            Log::error('Ошибка создания партнёрского платежа', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Ошибка создания платежа'], 500);
        }

        session([
            'partner_client_id' => $client->id,
            'partner_payment_id' => $result['payment_id'],
        ]);

        return response()->json([
            'success' => true,
            'payment_url' => $result['payment_url'],
            'payment_id' => $result['payment_id'],
        ]);
    }

    /**
     * Поллинг статуса оплаты
     * GET /api/partner/payment-status/{paymentId}
     */
    public function checkPaymentStatus(int $paymentId): JsonResponse
    {
        $clientId = session('partner_client_id');
        $sessionPaymentId = session('partner_payment_id');

        if (!$clientId || $sessionPaymentId != $paymentId) {
            return response()->json(['success' => false, 'message' => 'Сессия истекла'], 400);
        }

        $payment = Payment::where('id', $paymentId)
            ->where('client_id', $clientId)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Платёж не найден'], 404);
        }

        if (!$payment->isPaid() && $payment->canBeProcessed()) {
            $this->checkSbpStatus($payment);
            $payment->refresh();
        }

        return response()->json([
            'success' => true,
            'is_paid' => $payment->isPaid(),
            'status' => $payment->status,
            'redirect' => $payment->isPaid() ? route('auth.steam') : null,
        ]);
    }

    /**
     * Создать платёж через SBP-терминал
     */
    private function createSbpPayment(Client $client, SubscriptionPlan $plan, string $merchantOrderId): array
    {
        $baseUrl = config('sbp.base_url', env('SBP_BASE_URL'));
        $apiKey = env('SBP_API_KEY');
        $username = env('SBP_USERNAME');
        $password = env('SBP_PASSWORD');
        $amount = (float) $plan->price;

        $payment = Payment::create([
            'client_id' => $client->id,
            'order_id' => $merchantOrderId,
            'merchant_order_id' => $merchantOrderId,
            'amount' => $amount,
            'currency' => 'RUB',
            'status' => Payment::STATUS_CREATED,
            'payment_type' => Payment::TYPE_CARD,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        $http = Http::timeout(30)
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
            ]);

        $createResponse = $http->post($baseUrl . '/payments/create', [
            'MerchantOrderId' => $merchantOrderId,
            'Currency' => 'RUB',
            'Amount' => (int) round($amount * 100),
            'Type' => 'PayIn',
            'PaymentTypes' => ['IPS'],
            'CallbackUrl' => route('webhook.payment'),
            'LifeTime' => 900,
            'IsForm' => false,
        ]);

        if ($createResponse->failed()) {
            $payment->markAsFailed();
            throw new \Exception('Ошибка создания заказа: ' . $createResponse->body());
        }

        $createData = $createResponse->json();
        $orderId = $createData['Order']['OrderId'] ?? null;

        if (!$orderId) {
            $payment->markAsFailed();
            throw new \Exception('Не получен OrderId от эквайринга');
        }

        $qrResponse = Http::timeout(30)
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
            ])
            ->post($baseUrl . '/payments/ips/qrcData', [
                'QrcType' => '02',
                'TemplateVersion' => '01',
                'QrTtl' => '15',
                'MediaType' => 'image/svg+xml',
                'OrderId' => $orderId,
            ]);

        if ($qrResponse->failed()) {
            $payment->markAsFailed();
            throw new \Exception('Ошибка получения QR-кода: ' . $qrResponse->body());
        }

        $qrData = $qrResponse->json();

        if (!($qrData['Response']['Success'] ?? true)) {
            $payment->markAsFailed();
            throw new \Exception('Ошибка API: ' . ($qrData['Response']['ErrMessage'] ?? 'Unknown error'));
        }

        $payloadUrl = $qrData['Qrc']['Payload'] ?? null;

        $payment->update([
            'merchant_order_id' => $orderId,
            'status' => 'pending',
        ]);

        return [
            'payment_id' => $payment->id,
            'order_id' => $orderId,
            'payment_url' => $payloadUrl,
        ];
    }

    /**
     * Проверить статус платежа через SBP-терминал
     */
    private function checkSbpStatus(Payment $payment): void
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth(env('SBP_USERNAME'), env('SBP_PASSWORD'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => env('SBP_API_KEY'),
                ])
                ->post(env('SBP_BASE_URL') . '/payments/get', [
                    'OrderId' => $payment->merchant_order_id,
                ]);

            if (!$response->successful()) return;

            $status = $response->json('Order.Status');

            if (in_array($status, ['CHARGED', 'IPS_ACCEPTED'])) {
                $payment->markAsPaid();

                $plan = $payment->payable;
                if ($plan instanceof SubscriptionPlan) {
                    $subscriptionService = app(\App\Services\SubscriptionService::class);
                    $subscriptionService->purchase($payment->client, $plan, $payment->id);
                }
            } elseif (in_array($status, ['DECLINED', 'EXPIRED', 'CHARGE_DECLINED'])) {
                $payment->markAsFailed();
            }
        } catch (\Exception $e) {
            Log::error('SBP status check error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Получить QR URL по существующему OrderId
     */
    private function getQrUrl(string $orderId): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth(env('SBP_USERNAME'), env('SBP_PASSWORD'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => env('SBP_API_KEY'),
                ])
                ->post(env('SBP_BASE_URL') . '/payments/ips/qrcData', [
                    'QrcType' => '02',
                    'TemplateVersion' => '01',
                    'QrTtl' => '15',
                    'MediaType' => 'image/svg+xml',
                    'OrderId' => $orderId,
                ]);

            return $response->json('Qrc.Payload');
        } catch (\Exception $e) {
            return null;
        }
    }
}
