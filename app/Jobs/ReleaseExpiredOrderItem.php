<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Transaction;

class ReleaseExpiredOrderItem implements ShouldQueue
{
    use Queueable;

    private int $orderItemId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderItemId)
    {
        $this->orderItemId = $orderItemId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orderItem = OrderItem::with(['order.buyer', 'listing'])->find($this->orderItemId);
        
        if (!$orderItem) {
            Log::error("Order item {$this->orderItemId} not found");
            return;
        }

        // Проверяем, что элемент все еще зарезервирован и истек
        if ($orderItem->status !== OrderItem::STATUS_RESERVED) {
            return;
        }
        
        if ($orderItem->reserved_until > now()) {
            return;
        }

        DB::transaction(function () use ($orderItem) {
            // 1. Отменяем order_item
            $orderItem->cancel('Резерв истек');

            // 2. Освобождаем листинг
            if ($orderItem->listing) {
                $orderItem->listing->activate();
            }

            // 3. Возвращаем деньги на баланс покупателя
            Transaction::create([
                'type' => Transaction::TYPE_REFUND,
                'amount' => $orderItem->price,
                'client_id' => $orderItem->order->buyer->id,
                'description' => "Возврат средств за {$orderItem->item_name} (истек резерв)"
            ]);

            // 4. Проверяем статус заказа и обновляем если нужно
            $this->updateOrderStatus($orderItem->order);
        });
    }

    /**
     * Обновить статус заказа на основе статусов его элементов
     */
    private function updateOrderStatus(Order $order): void
    {
        $items = $order->items;
        
        $completedCount = $items->where('status', OrderItem::STATUS_COMPLETED)->count();
        $cancelledCount = $items->where('status', OrderItem::STATUS_CANCELLED)->count();
        $totalCount = $items->count();

        if ($completedCount === $totalCount) {
            // Все элементы завершены
            $order->update(['status' => Order::STATUS_COMPLETED]);
        } elseif ($cancelledCount === $totalCount) {
            // Все элементы отменены
            $order->update(['status' => Order::STATUS_CANCELLED]);
        } elseif ($completedCount > 0 && $cancelledCount > 0) {
            // Есть и завершенные и отмененные элементы
            $order->update(['status' => Order::STATUS_PARTIALLY_COMPLETED]);
        }
        // Если есть еще резервы или трейды в процессе - оставляем статус как есть
    }
}