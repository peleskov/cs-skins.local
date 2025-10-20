<?php

namespace App\Jobs;

use Exception;
use App\Models\Client;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        private Client $client,
        private string $type,
        private array $data
    ) {}

    public function middleware(): array
    {
        return [new RateLimited('email-notifications')];
    }

    public function handle(): void
    {
        if (!$this->client->email) {
            Log::channel('notifications')->warning('EMAIL_NO_ADDRESS', [
                'client_id' => $this->client->id,
                'type' => $this->type
            ]);
            return;
        }

        try {
            $startTime = microtime(true);

            $subject = $this->getSubject();
            $content = $this->getContent();

            Mail::raw($content, function ($message) use ($subject) {
                $message->to($this->client->email, $this->client->name)
                        ->subject($subject);
            });

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('notifications')->info('EMAIL_SENT', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'email' => $this->client->email,
                'status' => 'success',
                'duration_ms' => $duration
            ]);

        } catch (Exception $e) {
            Log::channel('notifications')->error('EMAIL_FAILED', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'email' => $this->client->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    private function getSubject(): string
    {
        return match ($this->type) {
            NotificationService::TYPE_ORDER_STATUS => "Статус заказа #{$this->data['order_number']} изменен",
            NotificationService::TYPE_AUCTION_OUTBID => "Вашу ставку перебили",
            NotificationService::TYPE_BALANCE_CHANGE => "Изменение баланса",
            NotificationService::TYPE_ADMIN_ALERT => "Административное уведомление",
            default => "Уведомление с сайта"
        };
    }

    private function getContent(): string
    {
        $siteName = config('app.name');

        return match ($this->type) {
            NotificationService::TYPE_ORDER_STATUS => $this->getOrderStatusContent(),
            NotificationService::TYPE_AUCTION_OUTBID => $this->getAuctionOutbidContent(),
            NotificationService::TYPE_BALANCE_CHANGE => $this->getBalanceChangeContent(),
            NotificationService::TYPE_ADMIN_ALERT => $this->getAdminAlertContent(),
            default => "Здравствуйте, {$this->client->name}!\n\nУ вас новое уведомление.\n\nС уважением,\nКоманда {$siteName}"
        };
    }

    private function getOrderStatusContent(): string
    {
        $siteName = config('app.name');
        $orderNumber = $this->data['order_number'];
        $status = $this->data['new_status'];
        $role = $this->data['role'];

        $statusTexts = [
            'buyer' => [
                'paid' => 'оплачен и передан в обработку',
                'processing' => 'обрабатывается, ожидайте Trade Offer',
                'completed' => 'выполнен, предметы отправлены на ваш Trade URL',
                'cancelled' => 'отменен, средства возвращены на баланс'
            ],
            'seller' => [
                'paid' => 'создан и ожидает обработки. Запустите расширение',
                'processing' => 'в обработке, Trade Offer создается',
                'completed' => 'выполнен, средства зачислены на баланс',
                'cancelled' => 'отменен'
            ]
        ];

        $statusText = $statusTexts[$role][$status] ?? $status;

        return "Здравствуйте, {$this->client->name}!\n\n" .
               "Статус заказа #{$orderNumber} изменен: {$statusText}.\n\n" .
               "Сумма заказа: {$this->data['amount']} {$this->data['currency']}\n\n" .
               "С уважением,\nКоманда {$siteName}";
    }

    private function getAuctionOutbidContent(): string
    {
        $siteName = config('app.name');
        $itemName = $this->data['item_name'];
        $newPrice = $this->data['new_price'];

        return "Здравствуйте, {$this->client->name}!\n\n" .
               "Вашу ставку на \"{$itemName}\" перебили!\n" .
               "Новая цена: {$newPrice} ₽\n\n" .
               "Сделайте новую ставку, чтобы остаться в игре.\n\n" .
               "С уважением,\nКоманда {$siteName}";
    }

    private function getBalanceChangeContent(): string
    {
        $siteName = config('app.name');
        $amount = $this->data['amount'];
        $type = $this->data['type'];
        $newBalance = $this->data['new_balance'];
        $description = $this->data['description'] ?? '';

        $operation = match ($type) {
            'deposit' => 'пополнение',
            'withdrawal' => 'списание',
            'sale' => 'зачисление с продажи',
            'purchase' => 'списание за покупку',
            'refund' => 'возврат средств',
            default => $type
        };

        return "Здравствуйте, {$this->client->name}!\n\n" .
               "Операция: {$operation}\n" .
               "Сумма: {$amount} ₽\n" .
               "Новый баланс: {$newBalance} ₽\n" .
               ($description ? "Описание: {$description}\n" : '') . "\n" .
               "С уважением,\nКоманда {$siteName}";
    }

    private function getAdminAlertContent(): string
    {
        $siteName = config('app.name');
        $message = $this->data['message'];

        return "Административное уведомление:\n\n{$message}\n\n" .
               "Данные: " . json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n" .
               "Система {$siteName}";
    }
}
