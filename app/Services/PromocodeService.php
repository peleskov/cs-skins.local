<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Promocode;
use Illuminate\Support\Facades\DB;

class PromocodeService
{
    public function __construct(
        private BonusBalanceService $bonusBalanceService
    ) {}

    /**
     * Валидация промокода
     *
     * @return array{valid: bool, message: string, bonus_amount?: float, promocode?: Promocode}
     */
    public function validate(string $code, Client $client, float $depositAmount): array
    {
        $promocode = Promocode::where('code', $code)->first();

        if (!$promocode) {
            return [
                'valid' => false,
                'message' => 'Промокод не найден',
            ];
        }

        if (!$promocode->isActive()) {
            return [
                'valid' => false,
                'message' => 'Промокод недействителен',
            ];
        }

        if (!$promocode->hasUsesLeft()) {
            return [
                'valid' => false,
                'message' => 'Лимит использований промокода исчерпан',
            ];
        }

        if (!$promocode->canBeUsedByClient($client)) {
            return [
                'valid' => false,
                'message' => 'Вы уже использовали этот промокод',
            ];
        }

        if ($depositAmount < $promocode->min_deposit) {
            return [
                'valid' => false,
                'message' => "Минимальная сумма пополнения: {$promocode->min_deposit} ₽",
            ];
        }

        $bonusAmount = $promocode->calculateBonus($depositAmount);

        return [
            'valid' => true,
            'message' => 'Промокод действителен',
            'bonus_amount' => $bonusAmount,
            'promocode' => $promocode,
        ];
    }

    /**
     * Применить промокод после успешного пополнения
     */
    public function apply(Promocode $promocode, Client $client, Payment $payment): BonusTransaction
    {
        return DB::transaction(function () use ($promocode, $client, $payment) {
            $bonusAmount = $promocode->calculateBonus((float) $payment->amount);

            $description = $promocode->type === Promocode::TYPE_PERCENT
                ? "Бонус по промокоду {$promocode->code} ({$promocode->value}%)"
                : "Бонус по промокоду {$promocode->code}";

            $transaction = $this->bonusBalanceService->credit(
                $client,
                $bonusAmount,
                $description,
                $promocode->id,
                $payment->id
            );

            $promocode->incrementUsage();

            return $transaction;
        });
    }

    /**
     * Найти промокод по коду
     */
    public function findByCode(string $code): ?Promocode
    {
        return Promocode::where('code', $code)->first();
    }
}
