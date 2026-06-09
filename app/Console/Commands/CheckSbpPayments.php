<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * Разовый/ручной добор зависших СБП-платежей (IPS_ACCEPTED без зачисления).
 * В отличие от джобы PollPendingSbpPayments — без окна 6ч, проверяет все
 * created СБП-платежи (или ограниченные --days).
 */
class CheckSbpPayments extends Command
{
    protected $signature = 'payments:check-sbp {--days= : Ограничить created_at последними N днями (по умолчанию — все)} {--dry-run : Только показать список, не дёргать эквайринг}';

    protected $description = 'Проверка статуса зависших СБП-платежей в эквайринге и добор зачислений';

    public function handle(PaymentService $paymentService): int
    {
        $query = Payment::query()
            ->where('payment_type', Payment::TYPE_SBP)
            ->where('status', Payment::STATUS_CREATED);

        if ($days = $this->option('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $days));
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;
        $completed = 0;

        $query->orderBy('id')->chunkById(100, function ($payments) use ($paymentService, $dryRun, &$total, &$completed) {
            foreach ($payments as $payment) {
                $total++;

                if ($dryRun) {
                    $this->line("#{$payment->id} client={$payment->client_id} {$payment->amount}₽ created={$payment->created_at} order={$payment->merchant_order_id}");

                    continue;
                }

                try {
                    if ($paymentService->checkPaymentStatus($payment)) {
                        $completed++;
                        $this->info("#{$payment->id} обработан (client={$payment->client_id}, {$payment->amount}₽)");
                    }
                } catch (\Throwable $e) {
                    $this->error("#{$payment->id}: {$e->getMessage()}");
                }
            }
        });

        if ($dryRun) {
            $this->info("Найдено зависших СБП-платежей: {$total}");
        } else {
            $this->info("Проверено: {$total}, завершено: {$completed}");
        }

        return self::SUCCESS;
    }
}
