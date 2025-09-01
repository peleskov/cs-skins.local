<?php

namespace App\Events;

use App\Models\Auction;
use App\Models\AuctionBid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionBidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $auction;
    public $bid;

    public function __construct(Auction $auction, AuctionBid $bid)
    {
        $this->auction = $auction;
        $this->bid = $bid;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('auctions.all')
        ];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'auction' => [
                'id' => $this->auction->id,
                'current_price' => $this->auction->current_price,
                'bid_count' => $this->auction->bid_count,
                'last_bidder_id' => $this->auction->last_bidder_id,
                'ends_at' => $this->auction->ends_at
            ],
            'bid' => [
                'id' => $this->bid->id,
                'amount' => $this->bid->amount,
                'placed_at' => $this->bid->placed_at,
                'bidder' => [
                    'id' => $this->bid->bidder->id,
                    'name' => $this->bid->bidder->name,
                    'steam_avatar' => $this->bid->bidder->steam_avatar
                ]
            ]
        ];
    }
}