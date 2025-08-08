<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\CancelOrderService;

class ReleaseExpiredOrder implements ShouldQueue
{
    use Queueable;

    public $tries = 2;              // Максимум 2 попытки
    public $backoff = [30];         // 30 секунд между попытками
    public $timeout = 30;           // 30 секунд timeout на попытку

    private int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(CancelOrderService $cancelService): void
    {
        $order = Order::with(['buyer', 'tradeOffer'])->find($this->orderId);
        
        if (!$order) {
            Log::error("Order {$this->orderId} not found");
            return;
        }

        if ($order->status === Order::STATUS_CANCELLED || $order->status === Order::STATUS_COMPLETED) {
            return;
        }
        
        if (!$order->reserved_until || $order->reserved_until > now()) {
            return;
        }

        $result = $cancelService->cancelOrder($order, 'Время резерва истекло', $this->tries);
        
        if (!$result['success'] && !isset($result['requires_manual'])) {
            throw new \Exception($result['message']);
        }
    }

}