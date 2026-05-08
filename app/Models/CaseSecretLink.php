<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CaseSecretLink extends Model
{
    protected $fillable = [
        'token',
        'label',
        'expires_at',
        'max_visits',
        'visits_count',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_visits' => 'integer',
        'visits_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $link) {
            if (empty($link->token)) {
                $link->token = self::generateToken();
            }
        });
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(16);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_visits !== null && $this->visits_count >= $this->max_visits) {
            return false;
        }

        return true;
    }
}
