<?php

namespace App\Services;

use App\Jobs\SendLosReferidosEvent;
use App\Models\Payment;
use App\Models\Referral;
use Illuminate\Support\Facades\Log;

class LosReferidosService
{
    protected string $advId;
    protected string $hash;
    protected string $baseUrl;

    public function __construct()
    {
        $this->advId = config('services.losreferidos.adv_id');
        $this->hash = config('services.losreferidos.hash');
        $this->baseUrl = config('services.losreferidos.base_url');
    }

    /**
     * Регистрация — новый лид для партнёра
     */
    public function sendRegistration(Referral $referral): void
    {
        $this->dispatch($referral, 'registration');
    }

    /**
     * Пополнение баланса
     */
    public function sendDeposit(Referral $referral, Payment $payment): void
    {
        $this->dispatch($referral, 'deposit', $payment);
    }

    /**
     * Первая покупка подписки
     */
    public function sendSubscription(Referral $referral, Payment $payment): void
    {
        $this->dispatch($referral, 'subscription', $payment);
    }

    /**
     * Продление подписки (ребилл)
     */
    public function sendRebill(Referral $referral, Payment $payment): void
    {
        $this->dispatch($referral, 'rebill', $payment);
    }

    /**
     * Отмена / истечение подписки
     */
    public function sendUnsubscription(Referral $referral): void
    {
        $this->dispatch($referral, 'unsubscription');
    }

    /**
     * Формирование URL и диспатч Job
     */
    protected function dispatch(Referral $referral, string $goalName, ?Payment $payment = null): void
    {
        $partner = $referral->partner;

        if (!$partner || !$partner->is_active) {
            Log::channel('losreferidos')->info('Событие не отправлено: партнёр неактивен', [
                'referral_id' => $referral->id,
                'partner_id' => $partner?->id,
                'goal_name' => $goalName,
            ]);
            return;
        }

        $params = [
            'hash' => $this->hash,
            'goal_name' => $goalName,
            'client_id' => $referral->id, // external_id = referral.id
            'partner_id' => $referral->partner_id,
            'link_id' => $referral->link_id ?? '',
        ];

        $params['order_id'] = $payment ? $payment->id : 0;

        // Опциональные данные клиента
        $client = $referral->client;
        if ($client) {
            $params['client_name'] = $client->name ?? '';
        }

        $url = "{$this->baseUrl}/{$this->advId}/?" . http_build_query($params);

        Log::channel('losreferidos')->info('Создание задачи на отправку события в LR', [
            'referral_id' => $referral->id,
            'partner_id' => $partner->id,
            'goal_name' => $goalName,
            'url' => $url,
        ]);

        SendLosReferidosEvent::dispatch($url, $referral->id, $partner->id, $goalName);
    }
}
