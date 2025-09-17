<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBan extends Model
{
    protected $fillable = [
        'client_id',
        'banned_until',
        'reason',
        'banned_by',
    ];

    protected $casts = [
        'banned_until' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'banned_by');
    }

    public function isActive(): bool
    {
        return $this->banned_until === null || $this->banned_until->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('banned_until')
              ->orWhere('banned_until', '>', now());
        });
    }
}