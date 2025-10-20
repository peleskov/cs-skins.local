<?php

namespace App\Listeners;

use DateTime;
use App\Events\AuctionBidPlaced;
use App\Services\NotificationService;
use App\Models\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendAuctionNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(AuctionBidPlaced $event): void
    {
        $auction = $event->auction;
        $newBid = $event->bid;

        // Находим предыдущего лидера (если был)
        $previousBidderId = $auction->getOriginal('last_bidder_id');

        if ($previousBidderId && $previousBidderId !== $newBid->bidder_id) {
            $previousBidder = Client::find($previousBidderId);

            if ($previousBidder) {
                Log::channel('notifications')->info('AUCTION_OUTBID_NOTIFICATION', [
                    'auction_id' => $auction->id,
                    'previous_bidder_id' => $previousBidderId,
                    'new_bidder_id' => $newBid->bidder_id,
                    'new_price' => $newBid->amount
                ]);

                $this->notificationService->sendAuctionOutbidNotification(
                    $previousBidder,
                    $auction,
                    $newBid
                );
            }
        }
    }

    /**
     * Determine the time at which the listener should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(5);
    }
}
