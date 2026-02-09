<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseModel extends Model
{
    protected $table = 'cases';

    // Типы кейсов
    const TYPE_NORMAL = 'normal';
    const TYPE_FREE = 'free';
    const TYPE_LIMITED = 'limited';

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
        // Новые поля
        'case_type',
        'free_min_deposit',
        'free_opens_count',
        'available_until',
        'total_opens_limit',
        'total_opens_count',
        'label_hot',
        'label_new',
        'label_limited',
        'label_free',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'accumulated_fund' => 'decimal:2',
        'fund_percent' => 'decimal:2',
        'is_active' => 'boolean',
        // Новые поля
        'free_min_deposit' => 'decimal:2',
        'free_opens_count' => 'integer',
        'available_until' => 'datetime',
        'total_opens_limit' => 'integer',
        'total_opens_count' => 'integer',
        'label_hot' => 'boolean',
        'label_new' => 'boolean',
        'label_limited' => 'boolean',
        'label_free' => 'boolean',
    ];

    // Relationships

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

    public function opens(): HasMany
    {
        return $this->hasMany(CaseOpen::class, 'case_id');
    }

    // Type checks

    public function isNormal(): bool
    {
        return $this->case_type === self::TYPE_NORMAL;
    }

    public function isFree(): bool
    {
        return $this->case_type === self::TYPE_FREE;
    }

    public function isLimited(): bool
    {
        return $this->case_type === self::TYPE_LIMITED;
    }

    // Availability checks

    /**
     * Проверка доступности кейса (для limited кейсов)
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isLimited()) {
            // Проверяем дату
            if ($this->available_until && $this->available_until->isPast()) {
                return false;
            }

            // Проверяем лимит открытий
            if (!$this->hasOpensRemaining()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Остались ли открытия (для limited кейсов)
     */
    public function hasOpensRemaining(): bool
    {
        if (!$this->isLimited() || $this->total_opens_limit === null) {
            return true;
        }

        return $this->total_opens_count < $this->total_opens_limit;
    }

    /**
     * Увеличить счётчик открытий
     */
    public function incrementOpensCount(): void
    {
        $this->increment('total_opens_count');
    }

    /**
     * Получить оставшееся количество открытий
     */
    public function getRemainingOpens(): ?int
    {
        if (!$this->isLimited() || $this->total_opens_limit === null) {
            return null;
        }

        return max(0, $this->total_opens_limit - $this->total_opens_count);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->where('case_type', '!=', self::TYPE_LIMITED)
                    ->orWhere(function ($q2) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('available_until')
                                ->orWhere('available_until', '>', now());
                        })->where(function ($q3) {
                            $q3->whereNull('total_opens_limit')
                                ->orWhereColumn('total_opens_count', '<', 'total_opens_limit');
                        });
                    });
            });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('case_type', $type);
    }

    // Static helpers

    public static function getTypes(): array
    {
        return [
            self::TYPE_NORMAL => 'Обычный',
            self::TYPE_FREE => 'Бесплатный',
            self::TYPE_LIMITED => 'Лимитированный',
        ];
    }
}
