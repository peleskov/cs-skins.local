<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRiggingPreset extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'price_percent',
        'chance_percent',
        'sort_order',
    ];

    protected $casts = [
        'price_percent' => 'decimal:2',
        'chance_percent' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
