<?php

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeReserved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderItem;

    public function __construct(OrderItem $orderItem)
    {
        $this->orderItem = $orderItem;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('seller-' . $this->orderItem->seller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trade_reserved';
    }

    public function broadcastWith(): array
    {
        return [
            'trade_id' => $this->orderItem->id,
            'listing_id' => $this->orderItem->listing_id,
            'item_name' => $this->orderItem->item_name,
            'price' => $this->orderItem->price,
            'buyer_name' => $this->orderItem->buyer_name,
            'reserved_until' => $this->orderItem->reserved_until->toISOString()
        ];
    }
}