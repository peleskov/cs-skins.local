<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class BonusBalanceService
{
    /**
     * Начислить бонус
     */
    public function credit(
        Client $client,
        float $amount,
        string $description,
        ?int $promocodeId = null,
        ?int $paymentId = null
    ): BonusTransaction {
        return DB::transaction(function () use ($client, $amount, $description, $promocodeId, $paymentId) {
            $client->creditBonus($amount);

            return BonusTransaction::create([
                'client_id' => $client->id,
                'type' => BonusTransaction::TYPE_CREDIT,
                'amount' => $amount,
                'description' => $description,
                'promocode_id' => $promocodeId,
                'payment_id' => $paymentId,
            ]);
        });
    }

    /**
     * Списать бонус
     */
    public function debit(
        Client $client,
        float $amount,
        string $description,
        ?int $caseId = null
    ): ?BonusTransaction {
        return DB::transaction(function () use ($client, $amount, $description, $caseId) {
            if (!$client->debitBonus($amount)) {
                return null;
            }

            return BonusTransaction::create([
                'client_id' => $client->id,
                'type' => BonusTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'description' => $description,
                'case_id' => $caseId,
            ]);
        });
    }

    /**
     * Получить историю бонусных транзакций
     */
    public function getHistory(Client $client, ?int $limit = null)
    {
        $query = $client->bonusTransactions()
            ->with(['promocode', 'payment', 'case'])
            ->orderByDesc('created_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
