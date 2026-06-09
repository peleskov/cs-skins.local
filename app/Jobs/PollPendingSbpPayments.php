<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Поллинг незавершённых СБП-платежей.
 *
 * По СБП эквайринг присылает webhook IPS_ACCEPTED (а не CHARGED), который
 * processWebhook не считает оплатой. Если клиент закрыл модалку до того, как
 * фронтовый поллинг поймал статус, платёж зависает в CREATED без зачисления.
 * Эта джоба добивает такие платежи через checkPaymentStatus (тот же путь, что и
 * фронтовый поллинг: IPS_ACCEPTED трактуется как оплачено).
 */
class PollPendingSbpPayments implements ShouldQueue
{
    use Queueable;

    public function handle(PaymentService $paymentService): void
    {
        // Только СБП в статусе CREATED, созданные за последние 6 часов
        // (lifetime формы — 30 мин; запас покрывает поздние/просроченные зачисления,
        // но не сканирует весь архив).
        $completed = 0;
        $total = 0;

        Payment::query()
            ->where('payment_type', Payment::TYPE_SBP)
            ->where('status', Payment::STATUS_CREATED)
            ->where('created_at', '>=', now()->subHours(6))
            ->chunkById(100, function ($payments) use ($paymentService, &$completed, &$total) {
                foreach ($payments as $payment) {
                    $total++;
                    try {
                        if ($paymentService->checkPaymentStatus($payment)) {
                            $completed++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('PollPendingSbpPayments: ошибка проверки платежа', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        if ($completed > 0) {
            Log::info("PollPendingSbpPayments: завершено платежей: {$completed} из {$total}");
        }
    }
}
