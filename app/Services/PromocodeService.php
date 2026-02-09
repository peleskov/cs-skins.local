<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Promocode;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Exception;

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
     * Активировать промокод напрямую (без пополнения)
     * Работает только для fixed промокодов — зачисляет value на основной баланс
     */
    public function activate(string $code, Client $client): array
    {
        $promocode = Promocode::where('code', $code)->first();

        if (!$promocode) {
            throw new Exception('Промокод не найден');
        }

        if (!$promocode->isActive()) {
            throw new Exception('Промокод недействителен');
        }

        if (!$promocode->hasUsesLeft()) {
            throw new Exception('Лимит использований промокода исчерпан');
        }

        if (!$promocode->canBeUsedByClient($client)) {
            throw new Exception('Вы уже использовали этот промокод');
        }

        if ($promocode->type !== Promocode::TYPE_FIXED) {
            throw new Exception('Этот промокод можно использовать только при пополнении баланса');
        }

        if ($promocode->min_deposit > 0) {
            throw new Exception('Этот промокод можно использовать только при пополнении баланса');
        }

        return DB::transaction(function () use ($promocode, $client) {
            $amount = (float) $promocode->value;

            // Зачисляем на основной баланс
            $client->credit($amount);

            // Создаём транзакцию
            Transaction::create([
                'client_id' => $client->id,
                'type' => Transaction::TYPE_PROMOCODE,
                'amount' => $amount,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Активация промокода {$promocode->code}",
            ]);

            // Инкрементим счётчик
            $promocode->incrementUsage();

            return [
                'amount' => $amount,
                'balance' => (float) $client->fresh()->balance,
            ];
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
