<?php

namespace App\Events;

use App\Models\CaseInventoryItem;
use App\Models\CaseModel;
use App\Models\CaseOpen;
use App\Models\Client;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CaseDropEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $broadcastQueue = null;
    public $afterCommit = true;

    public array $dropData;

    public function __construct(CaseOpen $caseOpen)
    {
        $client = $caseOpen->client;
        $case = $caseOpen->case;
        $inventoryItem = $caseOpen->inventoryItem;
        $virtualItem = $inventoryItem->virtualItem;

        $this->dropData = [
            'id' => $caseOpen->id,
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'avatar' => $client->steam_avatar,
            ],
            'case' => [
                'id' => $case->id,
                'name' => $case->name,
                'image' => $case->image_url,
            ],
            'item' => [
                'id' => $inventoryItem->id,
                'name' => $virtualItem->name,
                'price' => (float) $inventoryItem->price,
                'image_url' => $virtualItem->image_url,
                'rarity' => $virtualItem->rarity,
                'quality' => $virtualItem->quality,
            ],
            'timestamp' => $caseOpen->created_at->toISOString(),
        ];
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('presence-chat');
    }

    public function broadcastAs(): string
    {
        return 'case.drop';
    }

    public function broadcastWith(): array
    {
        return $this->dropData;
    }
}
