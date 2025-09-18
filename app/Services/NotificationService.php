<?php

namespace App\Services;

use App\Models\Client;
use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    const TYPE_ORDER_STATUS = 'order_status';
    const TYPE_AUCTION_OUTBID = 'auction_outbid';
    const TYPE_BALANCE_CHANGE = 'balance_change';
    const TYPE_ADMIN_ALERT = 'admin_alert';

    public function send(Client $client, string $type, array $data, array $channels = null)
    {
        Log::channel('notifications')->info('NOTIFICATION_SEND_START', [
            'client_id' => $client ? $client->id : null,
            'type' => $type,
            'data' => $data
        ]);

        if (!$client) {
            Log::channel('notifications')->warning('CLIENT_NULL', [
                'type' => $type,
                'data' => $data
            ]);
            return;
        }

        // Определяем каналы из настроек пользователя
        $channels = $channels ?? $this->getEnabledChannels($client, $type);

        Log::channel('notifications')->info('CHANNELS_DETERMINED', [
            'client_id' => $client->id,
            'type' => $type,
            'channels' => $channels,
            'notification_settings' => $client->notification_settings
        ]);

        foreach ($channels as $channel) {
            $this->sendToChannel($client, $channel, $type, $data);
        }
    }

    private function getEnabledChannels(Client $client, string $type): array
    {
        $settings = $client->notification_settings ?? [];
        $channels = [];

        // Если настройки в простом формате ["telegram", "email"]
        if (is_array($settings) && !empty($settings)) {
            // Проверяем Email
            if (in_array('email', $settings)) {
                $channels[] = 'email';
            }

            // Проверяем Telegram
            if (in_array('telegram', $settings) && $client->telegram_id) {
                $channels[] = 'telegram';
            }
        }

        return $channels;
    }

    private function isChannelEnabled(array $settings, string $channel, string $type): bool
    {
        $channelSettings = $settings[$channel] ?? [];

        return match ($type) {
            self::TYPE_ORDER_STATUS =>
                $channelSettings['order_buyer_updates'] ?? true ||
                $channelSettings['order_seller_updates'] ?? true,
            self::TYPE_AUCTION_OUTBID => $channelSettings['auction_updates'] ?? true,
            self::TYPE_BALANCE_CHANGE => $channelSettings['balance_changes'] ?? true,
            self::TYPE_ADMIN_ALERT => true, // Админские уведомления всегда включены
            default => false,
        };
    }

    private function sendToChannel(Client $client, string $channel, string $type, array $data)
    {
        try {
            match ($channel) {
                'email' => SendEmailNotificationJob::dispatch($client, $type, $data)
                    ->onQueue('notifications'),
                'telegram' => SendTelegramNotificationJob::dispatch($client, $type, $data)
                    ->onQueue('notifications'),
                default => Log::channel('notifications')->warning('UNKNOWN_CHANNEL', [
                    'channel' => $channel,
                    'client_id' => $client->id,
                    'type' => $type
                ])
            };

            Log::channel('notifications')->info('NOTIFICATION_QUEUED', [
                'client_id' => $client->id,
                'channel' => $channel,
                'type' => $type,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::channel('notifications')->error('NOTIFICATION_QUEUE_FAILED', [
                'client_id' => $client->id,
                'channel' => $channel,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Вспомогательные методы для конкретных типов уведомлений

    public function sendOrderStatusNotification(Client $client, $order, ?string $oldStatus, string $newStatus, string $role = 'buyer')
    {
        Log::channel('notifications')->info('NOTIFICATION_SERVICE_CALLED', [
            'client_id' => $client->id,
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'role' => $role
        ]);

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'role' => $role,
            'amount' => $order->total_amount,
            'currency' => $order->currency
        ];

        $this->send($client, self::TYPE_ORDER_STATUS, $data);
    }

    public function sendAuctionOutbidNotification(Client $client, $auction, $newBid)
    {
        $data = [
            'auction_id' => $auction->id,
            'item_name' => $auction->listing->item_name ?? 'Unknown',
            'old_price' => $auction->current_price,
            'new_price' => $newBid->amount,
            'bidder_name' => $newBid->bidder->name
        ];

        $this->send($client, self::TYPE_AUCTION_OUTBID, $data);
    }

    public function sendBalanceChangeNotification(Client $client, $transaction)
    {
        $data = [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'new_balance' => $client->balance,
            'description' => $transaction->description
        ];

        $this->send($client, self::TYPE_BALANCE_CHANGE, $data);
    }

    public function sendAdminAlert(string $message, array $data = [])
    {
        // Получаем всех админов (пользователей с is_admin = true или роль admin)
        $admins = Client::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            $alertData = array_merge($data, ['message' => $message]);
            $this->send($admin, self::TYPE_ADMIN_ALERT, $alertData);
        }
    }
}