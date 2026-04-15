<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'partner_id',
        'client_id',
        'link_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'link_id' => 'integer',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
