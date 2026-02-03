<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusTransaction extends Model
{
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'type',
        'amount',
        'description',
        'promocode_id',
        'payment_id',
        'case_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function promocode(): BelongsTo
    {
        return $this->belongsTo(Promocode::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
