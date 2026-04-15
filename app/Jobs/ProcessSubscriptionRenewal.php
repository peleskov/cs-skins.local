<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewal implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        try {
            Log::info('Обработка продления подписки', [
                'subscription_id' => $this->subscription->id,
                'client_id' => $this->subscription->client_id,
            ]);

            // Проверяем наличие токена
            if (!$this->subscription->subscription_token) {
                $payment = $this->subscription->payment;

                if (!$payment || !$payment->merchant_order_id) {
                    Log::error('Нет платежа или OrderId для получения токена', [
                        'subscription_id' => $this->subscription->id,
                    ]);
                    $this->subscription->cancel('no_subscription_token');
                    return;
                }

                $details = $paymentService->getSubscriptionDetails($payment->merchant_order_id);

                if (!$details || !$details['subscription_token']) {
                    Log::error('Не удалось получить данные подписки', [
                        'subscription_id' => $this->subscription->id,
                    ]);
                    $this->subscription->cancel('no_subscription_token');
                    return;
                }

                $this->subscription->update([
                    'subscription_token' => $details['subscription_token'],
                    'member_id' => $details['member_id'],
                ]);
            }

            // Списание
            $renewalPayment = $paymentService->chargeSubscriptionRenewal($this->subscription);

            Log::info('Автоматическое списание за продление инициировано', [
                'subscription_id' => $this->subscription->id,
                'renewal_payment_id' => $renewalPayment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка автоматического списания за продление', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);

            // Не отменяем подписку сразу — дадим ещё шанс на 3 и 7 день
        }
    }
}
