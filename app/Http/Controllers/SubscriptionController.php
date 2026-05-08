<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoginHistory;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SubscriptionController extends Controller
{
    /**
     * Список активных тарифов
     */
    public function plans(): JsonResponse
    {
        $client = $this->getClient();

        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) use ($client) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => (float) $plan->price,
                    'formatted_price' => $plan->getFormattedPrice(),
                    'duration_days' => $plan->duration_days,
                    'is_trial' => $plan->is_trial,
                    'trial_available' => $plan->is_trial && !$client->trial_used,
                ];
            });

        return response()->json(['success' => true, 'data' => $plans]);
    }

    /**
     * Статус подписки текущего пользователя
     */
    public function status(): JsonResponse
    {
        $client = $this->getClient();
        $subscription = $client->subscription;

        $premiumSettings = [
            'case_discount_low' => (float) SiteSetting::get('premium_case_discount_low', 10),
            'case_discount_high' => (float) SiteSetting::get('premium_case_discount_high', 5),
            'case_discount_threshold' => (float) SiteSetting::get('premium_case_discount_threshold', 500),
            'marketplace_fee' => (float) SiteSetting::get('premium_marketplace_fee', 6),
            'marketplace_fee_default' => (float) SiteSetting::get('marketplace_fee_percent', 5),
            'withdraw_fee' => (float) SiteSetting::get('premium_withdraw_fee', 6),
            'withdraw_fee_default' => (float) SiteSetting::get('withdraw_fee_percent', 7),
        ];

        if (!$subscription || !$subscription->isValid()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => false,
                    'trial_used' => $client->trial_used,
                    'has_pin_code' => !empty($client->pin_code),
                    'avatar_border_color' => $client->avatar_border_color,
                    'nickname_color' => $client->nickname_color,
                    'premium_settings' => $premiumSettings,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_active' => true,
                'auto_renewal' => (bool) $subscription->auto_renewal,
                'plan_name' => $subscription->plan?->name,
                'started_at' => $subscription->started_at->toISOString(),
                'expires_at' => $subscription->expires_at->toISOString(),
                'days_remaining' => $subscription->daysRemaining(),
                'settings' => $subscription->settings,
                'trial_used' => $client->trial_used,
                'has_pin_code' => !empty($client->pin_code),
                'avatar_border_color' => $client->avatar_border_color,
                'nickname_color' => $client->nickname_color,
                'premium_settings' => $premiumSettings,
            ],
        ]);
    }

    /**
     * Создать платёж за подписку
     */
    public function purchase(Request $request, PaymentService $paymentService): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $client = $this->getClient();

        if ($client->isBalanceBlocked()) {
            return response()->json([
                'success' => false,
                'message' => $client->getBalanceBlockReasonForUser() ?: 'Операции с балансом заблокированы',
            ], 403);
        }

        $plan = SubscriptionPlan::active()->findOrFail($request->plan_id);

        $result = $paymentService->createSubscriptionPayment($client, $plan);

        return response()->json($result);
    }

    /**
     * Проверить статус платежа за подписку
     */
    public function paymentStatus(int $paymentId, PaymentService $paymentService): JsonResponse
    {
        $client = $this->getClient();
        $payment = Payment::where('id', $paymentId)
            ->where('client_id', $client->id)
            ->firstOrFail();

        // Если платёж ещё не оплачен — проверяем у эквайринга
        if (!$payment->isPaid() && $payment->canBeProcessed()) {
            $paymentService->checkPaymentStatus($payment);
            $payment->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $payment->status,
                'is_paid' => $payment->isPaid(),
            ],
        ]);
    }

    /**
     * Переключить функцию подписки
     */
    public function toggleFeature(Request $request, SubscriptionService $subscriptionService): JsonResponse
    {
        $request->validate([
            'feature' => 'required|string',
            'enabled' => 'required|boolean',
            'pin_code' => 'nullable|string|size:4',
        ]);

        $client = $this->getClient();
        $subscription = $client->subscription;

        if (!$subscription || !$subscription->isValid()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        // При отключении код-пароля — проверяем текущий код
        if ($request->feature === 'pin_code' && !$request->enabled && !empty($client->pin_code)) {
            if (!$request->pin_code || !Hash::check($request->pin_code, $client->pin_code)) {
                return response()->json(['success' => false, 'message' => 'Неверный код-пароль'], 422);
            }
        }

        $subscriptionService->toggleFeature($subscription, $request->feature, $request->enabled);

        return response()->json([
            'success' => true,
            'settings' => $subscription->fresh()->settings,
        ]);
    }

    /**
     * Сохранить цвет обводки аватарки
     */
    public function setAvatarBorderColor(Request $request): JsonResponse
    {
        $request->validate([
            'color' => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $client = $this->getClient();

        if (!$client->isPremium()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $client->update(['avatar_border_color' => $request->color]);

        return response()->json(['success' => true, 'color' => $request->color]);
    }

    /**
     * Сохранить цвет никнейма
     */
    public function setNicknameColor(Request $request): JsonResponse
    {
        $request->validate([
            'color' => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $client = $this->getClient();

        if (!$client->isPremium()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $client->update(['nickname_color' => $request->color]);

        return response()->json(['success' => true, 'color' => $request->color]);
    }

    /**
     * Установить код-пароль
     */
    public function setPinCode(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code' => 'required|string|size:4|regex:/^\d{4}$/',
            'cooldown' => 'nullable|integer|min:0|max:10080',
        ]);

        $client = $this->getClient();

        if (!$client->isPremium()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $client->update([
            'pin_code' => Hash::make($request->pin_code),
        ]);

        // Сохраняем кулдаун в настройках подписки
        if ($request->has('cooldown')) {
            $subscription = $client->subscription;
            if ($subscription && $subscription->isValid()) {
                $settings = $subscription->settings ?? [];
                $settings['pin_code_cooldown'] = (int) $request->cooldown;
                $subscription->update(['settings' => $settings]);
            }
        }

        return response()->json([
            'success' => true,
            'pin_code' => $request->pin_code,
        ]);
    }

    /**
     * Верификация код-пароля при входе
     */
    public function verifyPinCode(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code' => 'required|string|size:4',
        ]);

        $clientId = session('pin_code_client_id');
        if (!$clientId) {
            return response()->json(['success' => false, 'message' => 'Сессия истекла, войдите заново'], 400);
        }

        $client = Client::find($clientId);
        if (!$client || empty($client->pin_code)) {
            return response()->json(['success' => false, 'message' => 'Ошибка'], 400);
        }

        if (!Hash::check($request->pin_code, $client->pin_code)) {
            LoginHistory::create([
                'client_id' => $client->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
            ]);
            return response()->json(['success' => false, 'message' => 'Неверный код-пароль']);
        }

        // Код верный — логиним, сохраняем время верификации и снимаем флаги
        $client->update(['pin_verified_at' => now()]);
        Auth::guard('client')->login($client, true);
        session()->forget(['pin_code_pending', 'pin_code_client_id']);

        LoginHistory::create([
            'client_id' => $client->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Удалить код-пароль
     */
    public function removePinCode(): JsonResponse
    {
        $client = $this->getClient();

        $client->update(['pin_code' => null, 'pin_verified_at' => null]);

        return response()->json(['success' => true]);
    }


    /**
     * Установить кулдаун код-пароля
     */
    public function setPinCodeCooldown(Request $request): JsonResponse
    {
        $request->validate([
            'cooldown' => 'required|integer|min:0|max:10080', // макс 7 дней
        ]);

        $client = $this->getClient();
        $subscription = $client->subscription;

        if (!$subscription || !$subscription->isValid()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $settings = $subscription->settings ?? [];
        $settings['pin_code_cooldown'] = (int) $request->cooldown;
        $subscription->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'cooldown' => $settings['pin_code_cooldown'],
        ]);
    }

    /**
     * История заходов на аккаунт
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $client = $this->getClient();

        if (!$client->isPremium()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [25, 50, 100])) $perPage = 25;

        $query = $client->loginHistories();

        if ($from = $request->get('from')) {
            try { $query->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay()); } catch (\Throwable $e) {}
        }
        if ($to = $request->get('to')) {
            try { $query->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay()); } catch (\Throwable $e) {}
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($entry) {
            return [
                'date' => $entry->created_at->format('d.m.Y H:i:s'),
                'ip' => $entry->ip_address,
                'device' => $entry->device,
                'status' => $entry->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Переключить автопродление
     */
    public function toggleAutoRenewal(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $client = $this->getClient();
        $subscription = $client->subscription;

        if (!$subscription || !$subscription->isValid()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $subscription->update(['auto_renewal' => $request->enabled]);

        SubscriptionService::log($subscription, 'auto_renewal_changed', $request->enabled ? 'Автопродление включено' : 'Автопродление отключено');

        return response()->json([
            'success' => true,
            'auto_renewal' => $subscription->auto_renewal,
        ]);
    }

    /**
     * Отменить подписку
     */
    public function cancel(SubscriptionService $subscriptionService): JsonResponse
    {
        $client = $this->getClient();
        $subscription = $client->subscription;

        if (!$subscription || !$subscription->isValid()) {
            return response()->json(['success' => false, 'message' => 'Подписка не активна'], 403);
        }

        $subscriptionService->expire($subscription);

        return response()->json(['success' => true]);
    }

    private function getClient(): Client
    {
        $client = auth('client')->user();

        if (!$client instanceof Client) {
            abort(401);
        }

        return $client;
    }
}
