<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'merchant_order_id',
        'order_id',
        'amount',
        'currency',
        'payment_type',
        'payment_url',
        'status',
        'webhook_data',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'webhook_data' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Status constants
    const STATUS_CREATED = 'created';
    const STATUS_PENDING = 'pending';
    const STATUS_AUTH3DS = 'auth3ds';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    // Payment type constants
    const TYPE_CARD = 'card';
    const TYPE_SBP = 'sbp';

    /**
     * Relationship with Client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Check if payment is completed
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is expired
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if payment can be processed
     */
    public function canBeProcessed(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_PENDING]) &&
               !$this->isExpired();
    }

    /**
     * Mark payment as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
        ]);
    }

    /**
     * Mark payment as expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Mark payment as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Generate unique order ID
     */
    public static function generateOrderId(): string
    {
        return 'cs_skins_' . time() . '_' . mt_rand(1000, 9999);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for active payments (created or pending)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CREATED, self::STATUS_PENDING]);
    }

    /**
     * Scope for expired payments that need cleanup
     */
    public function scopeExpiredPending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('expires_at', '<', now());
    }

    /**
     * Scope for client's payments
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}