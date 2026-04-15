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

    protected $appends = ['client_name', 'client_avatar', 'client_avatar_border_color', 'client_nickname_color', 'client_is_premium'];

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

    public function getClientAvatarBorderColorAttribute(): ?string
    {
        return $this->client->avatar_border_color ?? null;
    }

    public function getClientNicknameColorAttribute(): ?string
    {
        return $this->client->nickname_color ?? null;
    }

    public function getClientIsPremiumAttribute(): bool
    {
        return $this->client?->isPremium() ?? false;
    }
}