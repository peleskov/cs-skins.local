<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestToastNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'toast:test {user_id? : User ID to send test notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test toast notification system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $client = Client::find($userId);
            if (!$client) {
                $this->error("User with ID {$userId} not found");
                return 1;
            }
        } else {
            // Берем первого доступного пользователя
            $client = Client::first();
            if (!$client) {
                $this->error("No users found in database");
                return 1;
            }
        }

        $notificationService = app(NotificationService::class);

        $this->info("Sending test toast notification to user: {$client->name} (ID: {$client->id})");

        $notificationService->sendTestToastNotification($client, 'Это тестовое toast уведомление! 🚀');

        $this->info("Test notification sent successfully!");
        $this->info("Check the browser console and notifications to see if it was received.");

        return 0;
    }
}
