<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocode extends Model
{
    const TYPE_PERCENT = 'percent';

    const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_deposit',
        'max_uses',
        'max_uses_per_user',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'partner_id',
        'lr_offer_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_deposit' => 'decimal:2',
        'max_uses' => 'integer',
        'max_uses_per_user' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function bonusTransactions(): HasMany
    {
        return $this->hasMany(BonusTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function paidPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', Payment::STATUS_PAID);
    }

    /**
     * Проверка активности промокода
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Проверка лимита использований
     */
    public function hasUsesLeft(): bool
    {
        if ($this->max_uses === null) {
            return true;
        }

        return $this->used_count < $this->max_uses;
    }

    /**
     * Количество использований пользователем
     */
    public function getUsageCountByClient(Client $client): int
    {
        return $this->bonusTransactions()
            ->where('client_id', $client->id)
            ->where('type', BonusTransaction::TYPE_CREDIT)
            ->count();
    }

    /**
     * Может ли пользователь использовать промокод
     */
    public function canBeUsedByClient(Client $client): bool
    {
        return $this->getUsageCountByClient($client) < $this->max_uses_per_user;
    }

    /**
     * Рассчитать бонус
     */
    public function calculateBonus(float $depositAmount): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            return round($depositAmount * $this->value / 100, 2);
        }

        return (float) $this->value;
    }

    /**
     * Увеличить счетчик использований
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
