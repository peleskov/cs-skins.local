<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'client_id',
        'subscription_plan_id',
        'payment_id',
        'started_at',
        'expires_at',
        'is_active',
        'auto_renewal',
        'subscription_token',
        'member_id',
        'cancelled_reason',
        'settings',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_renewal' => 'boolean',
        'settings' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SubscriptionLog::class);
    }

    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) ceil(now()->diffInHours($this->expires_at) / 24);
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $settings = $this->settings ?? [];

        // По умолчанию все функции включены
        return $settings[$feature] ?? true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('is_active', true)
            ->where('auto_renewal', false)
            ->where('expires_at', '<=', now());
    }

    public function scopeDueForRenewal($query)
    {
        return $query->where('is_active', true)
            ->where('auto_renewal', true)
            ->where('expires_at', '<=', now());
    }

    public function cancel(string $reason = 'client_cancelled'): void
    {
        $this->update([
            'is_active' => false,
            'auto_renewal' => false,
            'cancelled_reason' => $reason,
        ]);
    }
}
