<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseModel extends Model
{
    protected $table = 'cases';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image_url',
        'accumulated_fund',
        'fund_percent',
        'is_active',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'accumulated_fund' => 'decimal:2',
        'fund_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(CaseTier::class, 'case_id')->orderByPrice();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CaseItem::class, 'case_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CaseCategory::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
