<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Upgrade extends Model
{
    const RESULT_WIN = 'win';
    const RESULT_LOSE = 'lose';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'bet_items',
        'bet_balance',
        'total_bet',
        'target_virtual_item_id',
        'target_price',
        'win_chance',
        'roll_value',
        'result',
        'won_item_id',
    ];

    protected $casts = [
        'bet_items' => 'array',
        'bet_balance' => 'decimal:2',
        'total_bet' => 'decimal:2',
        'target_price' => 'decimal:2',
        'win_chance' => 'decimal:2',
        'roll_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function targetVirtualItem(): BelongsTo
    {
        return $this->belongsTo(VirtualItem::class, 'target_virtual_item_id');
    }

    public function wonItem(): BelongsTo
    {
        return $this->belongsTo(CaseInventoryItem::class, 'won_item_id');
    }

    public function isWin(): bool
    {
        return $this->result === self::RESULT_WIN;
    }

    public function isLose(): bool
    {
        return $this->result === self::RESULT_LOSE;
    }
}
