<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use TelegramBot\Api\BotApi as TelegramApi;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        private Client $client,
        private string $type,
        private array $data
    ) {}

    public function middleware(): array
    {
        return [new RateLimited('telegram-notifications')];
    }

    public function handle(): void
    {
        if (!$this->client->telegram_id) {
            Log::channel('notifications')->warning('TELEGRAM_NO_ID', [
                'client_id' => $this->client->id,
                'type' => $this->type
            ]);
            return;
        }

        try {
            $startTime = microtime(true);

            $telegram = new TelegramApi(config('services.telegram.bot_token'));
            $message = $this->getMessage();

            $telegram->sendMessage(
                $this->client->telegram_id,
                $message,
                'HTML',
                true // disable_web_page_preview
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('notifications')->info('TELEGRAM_SENT', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'telegram_id' => $this->client->telegram_id,
                'status' => 'success',
                'duration_ms' => $duration
            ]);

        } catch (\Exception $e) {
            Log::channel('notifications')->error('TELEGRAM_FAILED', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'telegram_id' => $this->client->telegram_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    private function getMessage(): string
    {
        return match ($this->type) {
            NotificationService::TYPE_ORDER_STATUS => $this->getOrderStatusMessage(),
            NotificationService::TYPE_AUCTION_OUTBID => $this->getAuctionOutbidMessage(),
            NotificationService::TYPE_BALANCE_CHANGE => $this->getBalanceChangeMessage(),
            NotificationService::TYPE_ADMIN_ALERT => $this->getAdminAlertMessage(),
            default => "🔔 У вас новое уведомление"
        };
    }

    private function getOrderStatusMessage(): string
    {
        $orderNumber = $this->data['order_number'];
        $status = $this->data['new_status'];
        $role = $this->data['role'];
        $amount = $this->data['amount'];

        $statusMessages = [
            'buyer' => [
                'paid' => "✅ Заказ #{$orderNumber} оплачен и передан в обработку\n💰 Сумма: {$amount}₽",
                'processing' => "🔄 Заказ #{$orderNumber} обрабатывается\n⏳ Ожидайте Trade Offer",
                'completed' => "🎉 Заказ #{$orderNumber} выполнен!\n📦 Предметы отправлены на ваш Trade URL",
                'cancelled' => "❌ Заказ #{$orderNumber} отменен\n💰 Средства возвращены на баланс"
            ],
            'seller' => [
                'paid' => "📦 Новый заказ #{$orderNumber} на {$amount}₽!\n🔧 Запустите расширение для обработки",
                'processing' => "🔄 Заказ #{$orderNumber} в обработке\n⚙️ Trade Offer создается",
                'completed' => "✅ Заказ #{$orderNumber} выполнен!\n💰 Средства зачислены на баланс",
                'cancelled' => "❌ Заказ #{$orderNumber} отменен"
            ]
        ];

        return $statusMessages[$role][$status] ?? "📋 Статус заказа #{$orderNumber}: {$status}";
    }

    private function getAuctionOutbidMessage(): string
    {
        $itemName = $this->data['item_name'];
        $newPrice = $this->data['new_price'];
        $bidderName = $this->data['bidder_name'] ?? 'Аноним';

        return "😔 Вашу ставку на <b>{$itemName}</b> перебили!\n\n" .
               "💰 Новая цена: {$newPrice}₽\n" .
               "👤 Новый лидер: {$bidderName}\n\n" .
               "🔥 Сделайте новую ставку!";
    }

    private function getBalanceChangeMessage(): string
    {
        $amount = $this->data['amount'];
        $type = $this->data['type'];
        $newBalance = $this->data['new_balance'];

        $emoji = match ($type) {
            'deposit' => '💳',
            'withdrawal' => '💸',
            'sale' => '💰',
            'purchase' => '🛒',
            'refund' => '🔄',
            default => '💱'
        };

        $operation = match ($type) {
            'deposit' => 'Пополнение',
            'withdrawal' => 'Списание',
            'sale' => 'Зачисление с продажи',
            'purchase' => 'Списание за покупку',
            'refund' => 'Возврат средств',
            default => ucfirst($type)
        };

        return "{$emoji} <b>{$operation}</b>\n\n" .
               "💰 Сумма: {$amount}₽\n" .
               "💳 Новый баланс: {$newBalance}₽";
    }

    private function getAdminAlertMessage(): string
    {
        $message = $this->data['message'];

        return "🚨 <b>Административное уведомление</b>\n\n{$message}";
    }
}
