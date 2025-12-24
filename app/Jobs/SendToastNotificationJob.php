<?php

namespace App\Jobs;

use App\Models\Client;
use App\Events\ToastNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendToastNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        private Client $client,
        private string $type,
        private array $data
    ) {}

    public function handle(): void
    {
        try {
            $message = $this->formatMessage();
            $toastType = $this->getToastType();

            $noticeType = $this->getNoticeType();

            // Отправляем через WebSocket
            broadcast(new ToastNotificationEvent(
                $this->client->id,
                $message,
                $toastType,
                $this->data,
                $noticeType
            ));

            Log::channel('notifications')->info('TOAST_SENT', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'toast_type' => $toastType,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::channel('notifications')->error('TOAST_FAILED', [
                'client_id' => $this->client->id,
                'type' => $this->type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function formatMessage(): string
    {
        return match ($this->type) {
            'order_status' => $this->getOrderStatusMessage(),
            'auction_outbid' => $this->getAuctionOutbidMessage(),
            'balance_change' => $this->getBalanceChangeMessage(),
            'admin_alert' => $this->getAdminAlertMessage(),
            default => "У вас новое уведомление"
        };
    }

    private function getToastType(): string
    {
        return match ($this->type) {
            'order_status' => match ($this->data['new_status']) {
                'completed' => 'success',
                'cancelled' => 'warning',
                'paid' => 'info',
                default => 'info'
            },
            'auction_outbid' => 'warning',
            'balance_change' => 'info',
            'admin_alert' => 'error',
            default => 'info'
        };
    }

    private function getNoticeType(): ?string
    {
        $role = $this->data['role'] ?? null;
        $status = $this->data['new_status'] ?? null;

        return match ($this->type) {
            'order_status' => match (true) {
                // Продавец: новый заказ - нужно передать предмет
                $role === 'seller' && $status === 'paid' => 'saleTransfer',
                // Покупатель: предмет передан
                $role === 'buyer' && $status === 'processing' => 'purchaseReceive',
                // Обе стороны: успешное завершение
                $status === 'completed' => 'success',
                // Обе стороны: отмена
                $status === 'cancelled' => 'failed',
                default => 'other'
            },
            'auction_outbid' => 'auction',
            'balance_change' => 'other',
            'admin_alert' => 'other',
            default => 'other'
        };
    }

    private function getOrderStatusMessage(): string
    {
        $orderNumber = $this->data['order_number'];
        $status = $this->data['new_status'];
        $role = $this->data['role'];

        $messages = [
            'buyer' => [
                'paid' => "✅ Заказ #{$orderNumber} оплачен",
                'processing' => "🔄 Заказ #{$orderNumber} обрабатывается",
                'completed' => "🎉 Заказ #{$orderNumber} выполнен!",
                'cancelled' => "❌ Заказ #{$orderNumber} отменен"
            ],
            'seller' => [
                'paid' => "📦 Новый заказ #{$orderNumber}! Передайте предмет покупателю",
                'processing' => "🔄 Заказ #{$orderNumber} в обработке",
                'completed' => "✅ Заказ #{$orderNumber} завершен",
                'cancelled' => "❌ Заказ #{$orderNumber} отменен"
            ]
        ];

        return $messages[$role][$status] ?? "Статус заказа #{$orderNumber}: {$status}";
    }

    private function getAuctionOutbidMessage(): string
    {
        $itemName = $this->data['item_name'];
        $newPrice = $this->data['new_price'];

        return "😔 Вашу ставку на {$itemName} перебили! Новая цена: {$newPrice}₽";
    }

    private function getBalanceChangeMessage(): string
    {
        $amount = $this->data['amount'];
        $type = $this->data['type'];

        $operation = match ($type) {
            'deposit' => 'Пополнение',
            'withdrawal' => 'Списание',
            'sale' => 'Зачисление с продажи',
            'purchase' => 'Списание за покупку',
            'refund' => 'Возврат средств',
            default => 'Операция'
        };

        return "💰 {$operation}: {$amount}₽";
    }

    private function getAdminAlertMessage(): string
    {
        return "🚨 " . $this->data['message'];
    }
}