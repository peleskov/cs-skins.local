<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Services\AuctionService;
use Illuminate\Console\Command;
use Exception;

class CompleteExpiredAuctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auctions:complete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete all expired auctions';

    /**
     * Execute the console command.
     */
    public function handle(AuctionService $auctionService)
    {
        $this->info('Searching for expired auctions...');
        
        // Находим все активные аукционы с истекшим временем
        $expiredAuctions = Auction::where('status', Auction::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->with(['listing', 'seller', 'lastBidder'])
            ->get();

        if ($expiredAuctions->isEmpty()) {
            $this->info('No expired auctions found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiredAuctions->count()} expired auctions to process.");

        $completed = 0;
        $cancelled = 0;
        $errors = 0;

        foreach ($expiredAuctions as $auction) {
            try {
                $result = $auctionService->completeAuction($auction);
                
                if ($result) {
                    $completed++;
                    $this->info("✓ Auction #{$auction->id} completed, order #{$result->id} created");
                } else if ($auction->fresh()->status === Auction::STATUS_CANCELLED) {
                    $cancelled++;
                    $this->info("✓ Auction #{$auction->id} cancelled (no bids)");
                } else {
                    // Завершен, но без заказа (техническая ошибка)
                    $completed++;
                    $this->warn("⚠ Auction #{$auction->id} completed but order creation failed (funds refunded)");
                }
                
            } catch (Exception $e) {
                $errors++;
                $this->error("✗ Error completing auction #{$auction->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Processing completed:");
        $this->info("- Completed with orders: {$completed}");
        $this->info("- Cancelled (no bids): {$cancelled}");
        $this->info("- Errors: {$errors}");

        return Command::SUCCESS;
    }
}
