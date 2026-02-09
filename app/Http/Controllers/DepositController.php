<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Promocode;
use App\Services\PaymentService;
use App\Services\PromocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class DepositController extends Controller
{
    private PaymentService $paymentService;
    private PromocodeService $promocodeService;

    public function __construct(PaymentService $paymentService, PromocodeService $promocodeService)
    {
        $this->paymentService = $paymentService;
        $this->promocodeService = $promocodeService;
    }

    /**
     * Activate promocode directly (without deposit)
     */
    public function activatePromocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $client = Auth::guard('client')->user();

        try {
            $result = $this->promocodeService->activate($request->input('code'), $client);

            return response()->json([
                'success' => true,
                'message' => 'Промокод активирован',
                'amount' => $result['amount'],
                'balance' => $result['balance'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validate promocode
     */
    public function validatePromocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $client = Auth::guard('client')->user();
        $code = $request->input('code');
        $amount = (float) $request->input('amount');

        $result = $this->promocodeService->validate($code, $client, $amount);

        return response()->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'bonus_amount' => $result['bonus_amount'] ?? null,
        ]);
    }

    /**
     * Show deposit page
     */
    public function index(): View
    {
        $client = Auth::guard('client')->user();

        return view('deposit.index', [
            'client' => $client,
            'minimumAmount' => (float) \App\Models\SiteSetting::get('minimum_deposit_amount', 100),
        ]);
    }


    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, int $paymentId): JsonResponse
    {
        $client = Auth::guard('client')->user();

        $payment = Payment::where('id', $paymentId)
                          ->where('client_id', $client->id)
                          ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Платеж не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_type' => $payment->payment_type,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Check payment status with acquiring system
     */
    public function checkPaymentStatus(Request $request, int $paymentId): JsonResponse
    {
        $client = Auth::guard('client')->user();

        $payment = Payment::where('id', $paymentId)
                          ->where('client_id', $client->id)
                          ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Платеж не найден',
            ], 404);
        }

        // Check status with acquiring system
        $updated = $this->paymentService->checkPaymentStatus($payment);

        // Refresh payment from database
        $payment->refresh();

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_type' => $payment->payment_type,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get client's payments history
     */
    public function getPaymentsHistory(Request $request): JsonResponse
    {
        $client = Auth::guard('client')->user();

        $payments = Payment::where('client_id', $client->id)
                          ->orderBy('created_at', 'desc')
                          ->paginate(10);

        return response()->json([
            'success' => true,
            'payments' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Handle payment webhook from ArCoPay
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('Payment webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->getContent(),
                'query' => $request->query->all(),
                'post' => $request->post(),
            ]);


            // Regular ArCoPay webhook processing
            $signature = $request->header('Payment-Sign');
            $payload = $request->getContent();

            // Verify signature
            if (!$signature) {
                Log::error('Webhook signature missing');
                return response()->json(['status' => 'error', 'message' => 'Signature missing'], 400);
            }

            if (!$this->paymentService->verifyWebhookSignature($payload, $signature)) {
                Log::error('Webhook signature verification failed', [
                    'signature' => $signature,
                    'payload' => $payload,
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            // Parse and process webhook
            $webhookData = json_decode($payload, true);
            if (!$webhookData) {
                Log::error('Invalid webhook JSON', ['payload' => $payload]);
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON'], 400);
            }

            $processed = $this->paymentService->processWebhook($webhookData);

            return response()->json([
                'status' => $processed ? 'success' : 'error',
                'message' => $processed ? 'Webhook processed' : 'Processing failed',
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }
    }


    /**
     * Cancel payment
     */
    public function cancelPayment(Request $request, int $paymentId): JsonResponse
    {
        $client = Auth::guard('client')->user();

        $payment = Payment::where('id', $paymentId)
                          ->where('client_id', $client->id)
                          ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Платеж не найден',
            ], 404);
        }

        if (!$payment->canBeProcessed()) {
            return response()->json([
                'success' => false,
                'message' => 'Платеж нельзя отменить',
            ], 400);
        }

        $payment->markAsCancelled();

        return response()->json([
            'success' => true,
            'message' => 'Платеж отменен',
        ]);
    }

    /**
     * Create payment form for card deposit
     */
    public function createPaymentForm(Request $request): JsonResponse
    {
        $minAmount = (float) \App\Models\SiteSetting::get('minimum_deposit_amount', 100);
        $maxAmount = (float) \App\Models\SiteSetting::get('maximum_deposit_amount', 50000);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . $minAmount, 'max:' . $maxAmount],
            'success_url' => ['nullable', 'url'],
            'fail_url' => ['nullable', 'url'],
            'promocode' => ['nullable', 'string', 'max:100'],
            'payment_type' => ['nullable', 'string', 'in:card,test'],
        ], [
            'amount.required' => 'Укажите сумму пополнения',
            'amount.numeric' => 'Сумма должна быть числом',
            'amount.min' => "Минимальная сумма пополнения: {$minAmount} руб.",
            'amount.max' => "Максимальная сумма пополнения: {$maxAmount} руб.",
            'success_url.url' => 'Некорректный URL для успешного платежа',
            'fail_url.url' => 'Некорректный URL для неуспешного платежа',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $client = Auth::guard('client')->user();
        $amount = (float) $request->input('amount');
        $successUrl = $request->input('success_url');
        $failUrl = $request->input('fail_url');
        $promocodeInput = $request->input('promocode');
        $paymentType = $request->input('payment_type', 'card');

        // Validate promocode if provided
        $promocodeId = null;
        if ($promocodeInput) {
            $promoResult = $this->promocodeService->validate($promocodeInput, $client, $amount);
            if (!$promoResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $promoResult['message'],
                ], 422);
            }
            $promocodeId = $promoResult['promocode']->id;
        }

        // Create payment form
        $result = $this->paymentService->createPaymentForm($client, $amount, $successUrl, $failUrl, $promocodeId, $paymentType);

        return response()->json($result);
    }


    /**
     * Handle throttle exception and return localized message
     */
    protected function buildFailedValidationResponse(Request $request, array $errors)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Слишком много попыток. Попробуйте через несколько минут.',
            ], 429);
        }

        return parent::buildFailedValidationResponse($request, $errors);
    }
}