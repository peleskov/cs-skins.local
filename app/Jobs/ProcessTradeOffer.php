<?php

namespace App\Jobs;

use App\Events\ExtensionEvents;
use App\Models\TradeOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTradeOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TradeOffer $tradeOffer;

    /**
     * Create a new job instance.
     */
    public function __construct(TradeOffer $tradeOffer)
    {
        $this->tradeOffer = $tradeOffer;
        
        // Используем очередь trade-offers
        $this->onQueue('trade-offers');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Перезагружаем модель для актуальных данных
        $this->tradeOffer->refresh();

        // Проверяем, готов ли TradeOffer к отправке
        if (!$this->tradeOffer->isReady()) {
            Log::info('TradeOffer не готов к отправке, откладываем', [
                'trade_offer_id' => $this->tradeOffer->id,
                'seller_id' => $this->tradeOffer->seller_id,
                'is_ready' => $this->tradeOffer->is_ready,
            ]);
            
            // Откладываем на 15 секунд
            $this->release(15);
            return;
        }

        // Проверяем, что статус все еще pending
        if (!$this->tradeOffer->isPending()) {
            Log::info('TradeOffer уже не в статусе pending, пропускаем', [
                'trade_offer_id' => $this->tradeOffer->id,
                'current_status' => $this->tradeOffer->status,
            ]);
            return;
        }

        // Меняем статус на dispatched
        $this->tradeOffer->markAsDispatched();

        // Проверяем доступность расширения перед отправкой трейда
        if (!$this->checkExtensionAvailability()) {
            Log::warning('Extension недоступно, повторяем через 30 секунд', [
                'trade_offer_id' => $this->tradeOffer->id,
                'seller_id' => $this->tradeOffer->seller_id,
                'attempts' => $this->attempts()
            ]);
            
            // Повторяем через 30 секунд
            $this->release(30);
            return;
        }

        Log::info('Отправляем TradeOffer в расширение', [
            'trade_offer_id' => $this->tradeOffer->id,
            'seller_id' => $this->tradeOffer->seller_id,
            'order_id' => $this->tradeOffer->order_id,
        ]);

        // Отправляем событие в расширение
        ExtensionEvents::tradeOfferCreated($this->tradeOffer);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Ошибка обработки TradeOffer', [
            'trade_offer_id' => $this->tradeOffer->id,
            'seller_id' => $this->tradeOffer->seller_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // При критической ошибке отменяем весь заказ
        $order = $this->tradeOffer->order;
        $order->cancel('Продавец не доступен в настоящий момент, нет возможности выполнить заказ');
    }
    
    /**
     * Проверка доступности расширения через ping-pong
     */
    private function checkExtensionAvailability(): bool
    {
        $sellerId = $this->tradeOffer->seller_id;
        
        // Отправляем ping
        ExtensionEvents::ping($sellerId);
        
        // Ждем pong в течение 5 секунд
        $timeout = 5; // секунд
        $start = time();
        
        while ((time() - $start) < $timeout) {
            if (\Cache::has("extension_available_{$sellerId}")) {
                \Cache::forget("extension_available_{$sellerId}");
                return true;
            }
            
            // Ждем 100ms перед следующей проверкой
            usleep(100000);
        }
        
        return false;
    }
}