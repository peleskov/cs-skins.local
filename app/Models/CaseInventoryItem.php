<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseInventoryItem extends Model
{
    const STATUS_AVAILABLE = 'available';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_SOLD = 'sold';
    const STATUS_UPGRADED = 'upgraded';

    const SOURCE_CASE = 'case';
    const SOURCE_UPGRADE = 'upgrade';

    protected $fillable = [
        'client_id',
        'virtual_item_id',
        'price',
        'source_type',
        'source_id',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function virtualItem(): BelongsTo
    {
        return $this->belongsTo(VirtualItem::class);
    }

    public function sourceCase(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'source_id')
            ->where('source_type', self::SOURCE_CASE);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    public function isUpgraded(): bool
    {
        return $this->status === self::STATUS_UPGRADED;
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Доступен',
            self::STATUS_WITHDRAWN => 'Выведен',
            self::STATUS_SOLD => 'Продан',
            self::STATUS_UPGRADED => 'Использован в апгрейде',
        ];
    }

    public static function getSourceTypes(): array
    {
        return [
            self::SOURCE_CASE => 'Кейс',
            self::SOURCE_UPGRADE => 'Апгрейд',
        ];
    }
}
