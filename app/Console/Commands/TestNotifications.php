<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {client_id?} {--type=order}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification system';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $clientId = $this->argument('client_id');
        $type = $this->option('type');

        if (!$clientId) {
            // Показываем список клиентов с telegram_id
            $clients = Client::whereNotNull('telegram_id')
                ->orWhereNotNull('email')
                ->select('id', 'name', 'email', 'telegram_id')
                ->limit(10)
                ->get();

            if ($clients->isEmpty()) {
                $this->error('No clients with email or telegram_id found');
                return 1;
            }

            $this->table(
                ['ID', 'Name', 'Email', 'Telegram ID'],
                $clients->map(fn($c) => [$c->id, $c->name, $c->email, $c->telegram_id])
            );

            $clientId = $this->ask('Enter client ID to test');
        }

        $client = Client::find($clientId);
        if (!$client) {
            $this->error("Client with ID {$clientId} not found");
            return 1;
        }

        $this->info("Testing notifications for: {$client->name}");
        $this->info("Email: " . ($client->email ?: 'not set'));
        $this->info("Telegram: " . ($client->telegram_id ?: 'not set'));

        try {
            switch ($type) {
                case 'order':
                    $this->testOrderNotification($notificationService, $client);
                    break;
                case 'auction':
                    $this->testAuctionNotification($notificationService, $client);
                    break;
                case 'balance':
                    $this->testBalanceNotification($notificationService, $client);
                    break;
                default:
                    $this->error("Unknown type: {$type}. Use: order, auction, balance");
                    return 1;
            }

            $this->info('✅ Test notifications sent! Check logs: storage/logs/notifications-' . date('Y-m-d') . '.log');

        } catch (\Exception $e) {
            $this->error('❌ Error sending notifications: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function testOrderNotification(NotificationService $notificationService, Client $client)
    {
        $mockOrder = (object) [
            'id' => 999,
            'order_number' => 'TEST-' . strtoupper(substr(uniqid(), -6)),
            'total_amount' => 1250.50,
            'currency' => 'RUB'
        ];

        $notificationService->sendOrderStatusNotification(
            $client,
            $mockOrder,
            'paid',
            'completed',
            'buyer'
        );

        $this->info('📧 Order notification sent (buyer: paid → completed)');
    }

    private function testAuctionNotification(NotificationService $notificationService, Client $client)
    {
        $mockAuction = (object) [
            'id' => 999,
            'current_price' => 1200.00,
            'listing' => (object) ['item_name' => 'AK-47 | Redline (Field-Tested)']
        ];

        $mockBid = (object) [
            'amount' => 1350.00,
            'bidder' => (object) ['name' => 'TestUser']
        ];

        $notificationService->sendAuctionOutbidNotification($client, $mockAuction, $mockBid);

        $this->info('🏆 Auction outbid notification sent');
    }

    private function testBalanceNotification(NotificationService $notificationService, Client $client)
    {
        $mockTransaction = (object) [
            'id' => 999,
            'type' => 'deposit',
            'amount' => 500.00,
            'description' => 'Test deposit'
        ];

        $notificationService->sendBalanceChangeNotification($client, $mockTransaction);

        $this->info('💰 Balance change notification sent');
    }
}
