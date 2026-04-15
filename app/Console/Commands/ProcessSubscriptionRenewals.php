<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubscriptionRenewal;
use App\Models\Subscription;
use App\Services\LosReferidosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Обработка автопродлений подписок и отмена просроченных';

    public function handle(): void
    {
        $this->info('Обработка автопродлений подписок...');

        $subscriptions = Subscription::with(['client', 'plan', 'payment'])
            ->where('is_active', true)
            ->where('auto_renewal', true)
            ->get();

        $processed = 0;
        $renewed = 0;
        $cancelled = 0;

        foreach ($subscriptions as $subscription) {
            $expiresAt = $subscription->expires_at;

            // Если подписка ещё не истекла — пропускаем
            if (now()->lt($expiresAt)) {
                continue;
            }

            $daysOverdue = (int) $expiresAt->diffInDays(now());

            // Пробуем списать на 0, 3, 7 день просрочки
            if (in_array($daysOverdue, [0, 3, 7])) {
                ProcessSubscriptionRenewal::dispatch($subscription);
                $renewed++;

                $this->info("Dispatched renewal для client {$subscription->client_id} (день {$daysOverdue})");
            } elseif ($daysOverdue > 7) {
                // Отменяем подписку — не удалось списать за 7 дней
                $subscription->cancel('payment_failed');
                $cancelled++;

                // LR-событие: unsubscription
                $client = $subscription->client;
                if ($client && $client->referral && $client->referral->is_active) {
                    try {
                        $lrService = app(LosReferidosService::class);
                        $lrService->sendUnsubscription($client->referral);
                    } catch (\Throwable $e) {
                        // Не прерываем
                    }
                }

                $this->warn("Отменена подписка client {$subscription->client_id} (просрочка {$daysOverdue} дн.)");

                Log::info('Подписка отменена по истечению времени', [
                    'subscription_id' => $subscription->id,
                    'client_id' => $subscription->client_id,
                    'days_overdue' => $daysOverdue,
                ]);
            }

            $processed++;
        }

        $this->info("Обработано: {$processed}, продлений: {$renewed}, отмен: {$cancelled}");

        Log::info('Обработка автопродлений завершена', [
            'processed' => $processed,
            'renewed' => $renewed,
            'cancelled' => $cancelled,
        ]);
    }
}
