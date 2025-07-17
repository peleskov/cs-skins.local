<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'listing_id',
    ];

    /**
     * Связь с клиентом
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Связь с товаром
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}