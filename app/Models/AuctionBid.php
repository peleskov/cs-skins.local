<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionBid extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'bidder_id',
        'amount',
        'placed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public $timestamps = false;

    protected static function booted()
    {
        static::creating(function ($bid) {
            if (!$bid->placed_at) {
                $bid->placed_at = now();
            }
        });
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'bidder_id');
    }

    public function scopeForAuction($query, $auctionId)
    {
        return $query->where('auction_id', $auctionId);
    }

    public function scopeByBidder($query, $bidderId)
    {
        return $query->where('bidder_id', $bidderId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('amount', 'desc')->orderBy('placed_at', 'desc');
    }

    public function getIsWinningAttribute(): bool
    {
        if ($this->auction->status !== Auction::STATUS_COMPLETED) {
            return false;
        }

        $highestBid = $this->auction->bids()->latest()->first();
        return $highestBid && $highestBid->id === $this->id;
    }

    public function getIsLeadingAttribute(): bool
    {
        if ($this->auction->status !== Auction::STATUS_ACTIVE) {
            return false;
        }

        return $this->auction->last_bidder_id === $this->bidder_id;
    }
}