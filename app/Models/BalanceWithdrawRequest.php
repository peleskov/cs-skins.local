<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceWithdrawRequest extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'client_id',
        'amount',
        'withdrawn_24h_snapshot',
        'withdrawn_1h_snapshot',
        'limit_exceeded',
        'status',
        'processed_by',
        'processed_at',
        'admin_comment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'withdrawn_24h_snapshot' => 'decimal:2',
        'withdrawn_1h_snapshot' => 'decimal:2',
        'limit_exceeded' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
