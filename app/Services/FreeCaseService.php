<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CaseModel;
use App\Models\CaseOpen;
use App\Models\Payment;

class FreeCaseService
{
    /**
     * Получить общую сумму успешных депозитов клиента
     */
    public function getTotalDeposits(Client $client): float
    {
        return (float) Payment::where('client_id', $client->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', now()->subDay())
            ->sum('amount');
    }

    /**
     * Использованные бесплатные открытия пользователем за 24ч
     */
    public function getUsedFreeOpens(Client $client, CaseModel $case): int
    {
        return CaseOpen::where('client_id', $client->id)
            ->where('case_id', $case->id)
            ->where('is_free', true)
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Всего использовано бесплатных открытий по кейсу (все пользователи)
     */
    public function getTotalUsedFreeOpens(CaseModel $case): int
    {
        return CaseOpen::where('case_id', $case->id)
            ->where('is_free', true)
            ->count();
    }

    /**
     * Сколько бесплатных открытий доступно для данного кейса
     * earned_opens = floor(total_deposits / free_min_deposit) — личный лимит по депозитам
     * free_opens_count — глобальный лимит на всех пользователей
     */
    public function getAvailableFreeOpens(Client $client, CaseModel $case): int
    {
        if (!$case->isFree()) {
            return 0;
        }

        $totalDeposits = $this->getTotalDeposits($client);
        $minDeposit = $case->free_min_deposit ?? 0;
        $globalLimit = $case->free_opens_count ?? 0;

        if ($minDeposit <= 0) {
            return 0;
        }

        // Личный лимит: каждые free_min_deposit₽ = 1 открытие
        $earnedOpens = (int) floor($totalDeposits / $minDeposit);

        // Использовано лично за 24ч
        $usedOpens = $this->getUsedFreeOpens($client, $case);
        $personalAvailable = max(0, $earnedOpens - $usedOpens);

        // Глобальный лимит: сколько осталось на всех
        if ($globalLimit > 0) {
            $globalUsed = $this->getTotalUsedFreeOpens($case);
            $globalRemaining = max(0, $globalLimit - $globalUsed);
            return min($personalAvailable, $globalRemaining);
        }

        return $personalAvailable;
    }

    /**
     * Может ли клиент открыть кейс бесплатно
     */
    public function canOpenFree(Client $client, CaseModel $case): bool
    {
        return $this->getAvailableFreeOpens($client, $case) > 0;
    }

    /**
     * Время когда количество открытий реально уменьшится
     * Перебираем депозиты от старого к новому, убираем по одному —
     * первый, при удалении которого earned уменьшается, даёт expires_at
     */
    public function getExpiresAt(Client $client, CaseModel $case): ?string
    {
        $minDeposit = $case->free_min_deposit ?? 0;
        if ($minDeposit <= 0) {
            return null;
        }

        $payments = Payment::where('client_id', $client->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', now()->subDay())
            ->orderBy('paid_at', 'asc')
            ->get(['amount', 'paid_at']);

        if ($payments->isEmpty()) {
            return null;
        }

        $total = $payments->sum('amount');
        $usedOpens = $this->getUsedFreeOpens($client, $case);
        $currentAvailable = max(0, (int) floor($total / $minDeposit) - $usedOpens);

        foreach ($payments as $payment) {
            $total -= $payment->amount;
            $newAvailable = max(0, (int) floor($total / $minDeposit) - $usedOpens);
            if ($newAvailable < $currentAvailable) {
                return $payment->paid_at->addDay()->toIso8601String();
            }
        }

        return null;
    }

    /**
     * Получить информацию о бесплатных открытиях для клиента
     */
    public function getFreeOpensInfo(Client $client, CaseModel $case): array
    {
        if (!$case->isFree()) {
            return [
                'available' => false,
                'reason' => 'not_free_case',
            ];
        }

        $totalDeposits = $this->getTotalDeposits($client);
        $minDeposit = $case->free_min_deposit ?? 0;
        $globalLimit = $case->free_opens_count ?? 0;

        if ($minDeposit <= 0) {
            return [
                'available' => false,
                'reason' => 'no_free_opens_configured',
            ];
        }

        // Личный лимит: каждые free_min_deposit₽ = 1 открытие
        $earnedOpens = (int) floor($totalDeposits / $minDeposit);

        if ($earnedOpens <= 0) {
            return [
                'available' => false,
                'reason' => 'insufficient_deposits',
                'current_deposits' => $totalDeposits,
                'required_deposits' => $minDeposit,
                'remaining' => $minDeposit - fmod($totalDeposits, $minDeposit),
            ];
        }

        $usedOpens = $this->getUsedFreeOpens($client, $case);
        $personalAvailable = max(0, $earnedOpens - $usedOpens);

        // Глобальный лимит
        $globalRemaining = null;
        if ($globalLimit > 0) {
            $globalUsed = $this->getTotalUsedFreeOpens($case);
            $globalRemaining = max(0, $globalLimit - $globalUsed);
            $personalAvailable = min($personalAvailable, $globalRemaining);
        }

        if ($personalAvailable <= 0) {
            $remainder = fmod($totalDeposits, $minDeposit);
            return [
                'available' => false,
                'reason' => $globalRemaining === 0 ? 'global_limit_reached' : 'no_opens_left',
                'earned_opens' => $earnedOpens,
                'used_opens' => $usedOpens,
                'required_deposits' => $minDeposit,
                'remaining' => $minDeposit - $remainder,
            ];
        }

        return [
            'available' => true,
            'opens_remaining' => $personalAvailable,
            'earned_opens' => $earnedOpens,
            'used_opens' => $usedOpens,
            'expires_at' => $this->getExpiresAt($client, $case),
        ];
    }
}
