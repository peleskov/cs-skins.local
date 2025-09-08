<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseTier extends Model
{
    protected $fillable = [
        'case_id',
        'name',
        'price',
        'probability',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'probability' => 'decimal:2',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CaseItem::class, 'tier_id');
    }

    public function scopeOrderByPrice($query, $direction = 'desc')
    {
        return $query->orderBy('price', $direction);
    }
}
