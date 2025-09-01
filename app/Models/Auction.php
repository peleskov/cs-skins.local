<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'listing_id',
        'starting_price',
        'current_price',
        'bid_count',
        'last_bidder_id',
        'min_bid_increment',
        'status',
        'starts_at',
        'ends_at',
        'auto_extend',
        'duration_hours',
    ];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'min_bid_increment' => 'decimal:2',
        'bid_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_extend' => 'boolean',
        'duration_hours' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'seller_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function lastBidder(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'last_bidder_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('ends_at', '>', now());
    }

    public function scopeEnded($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('ends_at', '<=', now());
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ends_at > now();
    }

    public function getIsEndedAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ends_at <= now();
    }

    public function getMinimumBidAttribute(): float
    {
        return $this->current_price + $this->min_bid_increment;
    }

    public function getBuyoutPriceAttribute(): ?float
    {
        return $this->listing->price;
    }

    public function canBid(Client $client): bool
    {
        if ($this->seller_id === $client->id) {
            return false;
        }

        if ($this->last_bidder_id === $client->id) {
            return false;
        }

        if (!$this->is_active) {
            return false;
        }

        return true;
    }

    public function shouldExtend(): bool
    {
        $timeUntilEnd = $this->ends_at->diffInMinutes(now());
        return $timeUntilEnd <= 5;
    }

    public function extend(int $minutes = 5): void
    {
        $this->ends_at = $this->ends_at->addMinutes($minutes);
        $this->save();
    }
}