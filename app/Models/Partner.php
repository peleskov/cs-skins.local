<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = [
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function clients()
    {
        return $this->hasManyThrough(Client::class, Referral::class, 'partner_id', 'id', 'id', 'client_id');
    }
}
