<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Subscription;
use App\Models\SubscriptionLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Активировать подписку после успешной оплаты
     */
    public function purchase(Client $client, SubscriptionPlan $plan, ?int $paymentId = null): Subscription
    {
        return DB::transaction(function () use ($client, $plan, $paymentId) {
            // Проверка триала
            if ($plan->is_trial && $client->trial_used) {
                throw new \Exception('Триал уже использован');
            }

            $activeSubscription = $client->subscription;

            if ($activeSubscription && $activeSubscription->isValid()) {
                // Суммируем дни к существующей подписке
                $this->extend($activeSubscription, $plan->duration_days, "Продление по тарифу «{$plan->name}»");
                $activeSubscription->update(['payment_id' => $paymentId]);
                $subscription = $activeSubscription;
            } else {
                // Деактивируем старую если была
                if ($activeSubscription) {
                    $this->expire($activeSubscription);
                }

                // Создаём новую подписку
                $subscription = Subscription::create([
                    'client_id' => $client->id,
                    'subscription_plan_id' => $plan->id,
                    'payment_id' => $paymentId,
                    'started_at' => now(),
                    'expires_at' => now()->addDays($plan->duration_days),
                    'is_active' => true,
                    'settings' => $this->getDefaultSettings(),
                ]);

                self::log($subscription, 'created', "Подписка оформлена по тарифу «{$plan->name}» на {$plan->duration_days} дн.");
            }

            // Отмечаем триал использованным
            if ($plan->is_trial) {
                $client->update(['trial_used' => true]);
            }

            // Транзакция
            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_SUBSCRIPTION,
                'amount' => $plan->price,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Оплата PREMIUM-подписки «{$plan->name}» ({$plan->duration_days} дн.)",
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'payment_id' => $paymentId,
                ],
            ]);

            Log::info('Подписка оформлена', [
                'client_id' => $client->id,
                'plan' => $plan->name,
                'expires_at' => $subscription->expires_at,
            ]);

            return $subscription;
        });
    }

    /**
     * Продлить подписку на N дней
     */
    public function extend(Subscription $subscription, int $days, ?string $reason = null, ?string $performedBy = null): void
    {
        $oldExpires = $subscription->expires_at->copy();
        $subscription->update([
            'expires_at' => $subscription->expires_at->addDays($days),
        ]);

        self::log($subscription, 'extended', $reason ?? "Продлена на {$days} дн.", [
            'days' => $days,
            'old_expires_at' => $oldExpires->toISOString(),
            'new_expires_at' => $subscription->expires_at->toISOString(),
        ], $performedBy);
    }

    /**
     * Деактивировать подписку
     */
    public function expire(Subscription $subscription, ?string $performedBy = null): void
    {
        $subscription->update(['is_active' => false]);

        self::log($subscription, 'expired', 'Подписка деактивирована', performedBy: $performedBy);

        // LR-событие: unsubscription
        $referral = $subscription->client->referral;
        if ($referral && $referral->is_active) {
            $lrService = app(\App\Services\LosReferidosService::class);
            $lrService->sendUnsubscription($referral);
        }
    }

    /**
     * Деактивация всех просроченных подписок
     */
    public function checkAndExpire(): int
    {
        $expired = Subscription::expired()->get();

        foreach ($expired as $subscription) {
            $this->expire($subscription);

            Log::info('Подписка истекла', [
                'subscription_id' => $subscription->id,
                'client_id' => $subscription->client_id,
                'expires_at' => $subscription->expires_at,
            ]);
        }

        return $expired->count();
    }

    /**
     * Настройки по умолчанию — все функции включены
     */
    public function getDefaultSettings(): array
    {
        return [
            'case_discount' => true,
            'marketplace_fee' => true,
            'withdraw_fee' => true,
            'avatar_border' => true,
            'anti_unluck' => true,
            'pin_code' => true,
            'pin_code_cooldown' => 0, // 0 = каждый вход, число = минуты
        ];
    }

    /**
     * Переключить функцию подписки
     */
    public function toggleFeature(Subscription $subscription, string $feature, bool $enabled): void
    {
        $settings = $subscription->settings ?? $this->getDefaultSettings();
        $settings[$feature] = $enabled;

        $subscription->update(['settings' => $settings]);

        $state = $enabled ? 'включена' : 'отключена';
        self::log($subscription, 'settings_changed', "Функция «{$feature}» {$state}");
    }

    /**
     * Записать лог действия с подпиской
     */
    public static function log(Subscription $subscription, string $action, string $description, ?array $metadata = null, ?string $performedBy = null): void
    {
        SubscriptionLog::create([
            'subscription_id' => $subscription->id,
            'client_id' => $subscription->client_id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => $performedBy,
        ]);
    }
}
