<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchSubscriptionToken implements ShouldQueue
{
    use Queueable;

    private const DELAYS = [
        1 => 10,    // 10 сек
        2 => 30,    // 30 сек
        3 => 60,    // 60 сек
        4 => 1200,  // 20 минут
    ];

    private const MAX_ATTEMPTS = 4;

    public function __construct(
        public Subscription $subscription,
        public int $attempt = 1
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        // Если токен уже есть — завершаем
        if ($this->subscription->subscription_token) {
            return;
        }

        $payment = $this->subscription->payment;

        if (!$payment || !$payment->merchant_order_id) {
            Log::warning('FetchSubscriptionToken: нет платежа или merchant_order_id', [
                'subscription_id' => $this->subscription->id,
            ]);
            return;
        }

        $details = $paymentService->getSubscriptionDetails($payment->merchant_order_id);

        if ($details && $details['subscription_token']) {
            $this->subscription->update([
                'subscription_token' => $details['subscription_token'],
                'member_id' => $details['member_id'],
            ]);

            Log::info('Токен подписки получен и сохранён', [
                'subscription_id' => $this->subscription->id,
                'attempt' => $this->attempt,
            ]);
            return;
        }

        // Токен не получен — планируем следующую попытку
        if ($this->attempt < self::MAX_ATTEMPTS) {
            $nextAttempt = $this->attempt + 1;
            $delay = self::DELAYS[$nextAttempt];

            self::dispatch($this->subscription, $nextAttempt)
                ->delay(now()->addSeconds($delay));

            Log::info('FetchSubscriptionToken: токен не готов, следующая попытка', [
                'subscription_id' => $this->subscription->id,
                'next_attempt' => $nextAttempt,
                'delay_seconds' => $delay,
            ]);
        } else {
            Log::warning('FetchSubscriptionToken: все попытки исчерпаны', [
                'subscription_id' => $this->subscription->id,
            ]);
        }
    }
}
