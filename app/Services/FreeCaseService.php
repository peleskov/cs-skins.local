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
            ->where('status', 'completed')
            ->where('type', 'deposit')
            ->sum('amount');
    }

    /**
     * Получить количество использованных бесплатных открытий для кейса
     * Считаем из таблицы case_opens
     */
    public function getUsedFreeOpens(Client $client, CaseModel $case): int
    {
        return CaseOpen::where('client_id', $client->id)
            ->where('case_id', $case->id)
            ->where('is_free', true)
            ->count();
    }

    /**
     * Сколько бесплатных открытий доступно для данного кейса
     * Формула по ТЗ: earned_opens = floor(total_deposits / free_min_deposit) * free_opens_count
     */
    public function getAvailableFreeOpens(Client $client, CaseModel $case): int
    {
        if (!$case->isFree()) {
            return 0;
        }

        $totalDeposits = $this->getTotalDeposits($client);
        $minDeposit = $case->free_min_deposit ?? 0;
        $opensPerDeposit = $case->free_opens_count ?? 0;

        if ($minDeposit <= 0 || $opensPerDeposit <= 0) {
            return 0;
        }

        // earned_opens = floor(total_deposits / free_min_deposit) * free_opens_count
        $earnedOpens = (int) floor($totalDeposits / $minDeposit) * $opensPerDeposit;

        // used_opens = COUNT(case_opens WHERE is_free = true)
        $usedOpens = $this->getUsedFreeOpens($client, $case);

        return max(0, $earnedOpens - $usedOpens);
    }

    /**
     * Может ли клиент открыть кейс бесплатно
     */
    public function canOpenFree(Client $client, CaseModel $case): bool
    {
        return $this->getAvailableFreeOpens($client, $case) > 0;
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
        $opensPerDeposit = $case->free_opens_count ?? 0;

        if ($minDeposit <= 0 || $opensPerDeposit <= 0) {
            return [
                'available' => false,
                'reason' => 'no_free_opens_configured',
            ];
        }

        // Заработанные открытия
        $earnedOpens = (int) floor($totalDeposits / $minDeposit) * $opensPerDeposit;

        if ($earnedOpens <= 0) {
            return [
                'available' => false,
                'reason' => 'insufficient_deposits',
                'current_deposits' => $totalDeposits,
                'required_deposits' => $minDeposit,
                'remaining' => $minDeposit - ($totalDeposits % $minDeposit),
            ];
        }

        $usedOpens = $this->getUsedFreeOpens($client, $case);
        $availableOpens = max(0, $earnedOpens - $usedOpens);

        if ($availableOpens <= 0) {
            return [
                'available' => false,
                'reason' => 'no_opens_left',
                'earned_opens' => $earnedOpens,
                'used_opens' => $usedOpens,
            ];
        }

        return [
            'available' => true,
            'opens_remaining' => $availableOpens,
            'earned_opens' => $earnedOpens,
            'used_opens' => $usedOpens,
        ];
    }
}
