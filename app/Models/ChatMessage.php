<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    const UPDATED_AT = null;

    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'client_id',
        'message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $appends = ['client_name', 'client_avatar'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getClientNameAttribute(): string
    {
        return $this->client->name ?? 'Unknown';
    }

    public function getClientAvatarAttribute(): ?string
    {
        return $this->client->steam_avatar ?? null;
    }
}