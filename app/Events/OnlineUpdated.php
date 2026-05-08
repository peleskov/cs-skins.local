<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class OnlineUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $broadcastQueue = null;

    public function __construct(public int $count) {}

    public function broadcastOn(): Channel
    {
        return new Channel('online');
    }

    public function broadcastAs(): string
    {
        return 'online.updated';
    }

    public function broadcastWith(): array
    {
        return ['count' => $this->count];
    }
}
