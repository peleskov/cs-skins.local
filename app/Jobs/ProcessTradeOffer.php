<?php

namespace App\Jobs;

use App\Models\TradeOffer;
use App\Models\Order;
use App\Services\Steam\TradeService;
use App\Services\Steam\SessionCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class ProcessTradeOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TradeOffer $tradeOffer;
    
    public $tries = 2;
    public $timeout = 300; // 5 минут

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
    public function handle(TradeService $tradeService, SessionCache $sessionCache): void
    {
        // Перезагружаем модель для актуальных данных
        $this->tradeOffer->refresh();

        // Проверяем, что статус все еще pending
        if (!$this->tradeOffer->isPending()) {
            Log::info('TradeOffer уже не в статусе pending, пропускаем', [
                'trade_offer_id' => $this->tradeOffer->id,
                'current_status' => $this->tradeOffer->status,
            ]);
            return;
        }

        $sellerId = $this->tradeOffer->seller_id;
        
        // Получаем блокировку продавца (Redis lock с TTL 5 минут)
        $lock = Cache::lock("trade:seller:{$sellerId}:processing", 300);
        
        if (!$lock->get()) {
            // Отложить с экспонентной задержкой
            $delay = min($this->attempts() * 10, 60);
            
            Log::info('Продавец занят обработкой другого трейда, откладываем', [
                'trade_offer_id' => $this->tradeOffer->id,
                'seller_id' => $sellerId,
                'delay' => $delay
            ]);
            
            $this->release($delay);
            return;
        }

        try {
            // Валидация перед обработкой
            $this->validateBeforeProcessing($sessionCache);
            
            Log::info('Создаем TradeOffer через Steam API', [
                'trade_offer_id' => $this->tradeOffer->id,
                'seller_id' => $sellerId,
                'buyer_id' => $this->tradeOffer->buyer_id,
                'order_id' => $this->tradeOffer->order_id,
            ]);
            
            // Создаем трейд через TradeService
            $result = $tradeService->createTradeOffer($this->tradeOffer);
            
            if ($result['success']) {
                // Обновляем статус заказа при необходимости
                if ($this->tradeOffer->fresh()->status === TradeOffer::STATUS_CREATED_NEEDS_CONFIRMATION) {
                    $this->tradeOffer->order->update([
                        'status' => Order::STATUS_PROCESSING,
                        'system_remarks' => 'Ожидаем подтверждения продавца'
                    ]);
                }
                
                Log::info('TradeOffer успешно создан', [
                    'trade_offer_id' => $this->tradeOffer->id,
                    'message' => $result['message']
                ]);
            } else {
                // Неуспешно - отменяем заказ
                $this->tradeOffer->order->cancel($result['message']);
                return;
            }
            
        } catch (Exception $e) {
            // Классификация ошибки и решение о повторе/отмене
            if ($this->attempts() >= $this->tries || $this->isCriticalError($e)) {
                Log::error('Критическая ошибка при создании трейда, отменяем заказ', [
                    'trade_offer_id' => $this->tradeOffer->id,
                    'error' => $e->getMessage(),
                    'attempts' => $this->attempts()
                ]);
                
                $this->tradeOffer->order->cancel("В настоящий момент продавец недоступен");
                return;
            }
            
            Log::warning('Временная ошибка при создании трейда, повторяем попытку', [
                'trade_offer_id' => $this->tradeOffer->id,
                'error' => $e->getMessage(),
                'attempts' => $this->attempts()
            ]);
            
            // Повторить попытку
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Ошибка обработки TradeOffer после всех попыток', [
            'trade_offer_id' => $this->tradeOffer->id,
            'seller_id' => $this->tradeOffer->seller_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // При критической ошибке отменяем весь заказ
        $this->tradeOffer->order->cancel('Продавец не доступен в настоящий момент, нет возможности выполнить заказ');
    }
    
    /**
     * Валидация перед обработкой
     */
    private function validateBeforeProcessing(SessionCache $sessionCache): void
    {
        // Проверяем статус трейда
        if (!$this->tradeOffer->isPending()) {
            throw new Exception('TradeOffer is not in pending status');
        }
        
        // Проверяем наличие сессии продавца
        if (!$sessionCache->has($this->tradeOffer->seller_id)) {
            throw new Exception('No cached Steam session available for seller');
        }
        
        // Проверяем время жизни сессии (не старше 5 минут)
        $sessionAge = $sessionCache->getExpiresInSeconds($this->tradeOffer->seller_id);
        if ($sessionAge <= 0) {
            throw new Exception('Steam session has expired');
        }
    }
    
    /**
     * Определение критических ошибок
     */
    private function isCriticalError(Exception $e): bool
    {
        $message = $e->getMessage();
        
        // Критические ошибки Steam
        $criticalErrors = [
            'TradeBan',
            'TargetCannotTrade', 
            'NewDevice',
            'Invalid trade URL',
            'Invalid input SteamID',
            'Not Logged In',
            'No cached Steam session',
            'Steam session has expired',
            'Seller or buyer not found',
            'Cannot send an empty trade offer',
            'This offer has already been sent'
        ];
        
        foreach ($criticalErrors as $error) {
            if (stripos($message, $error) !== false) {
                return true;
            }
        }
        
        // Временные ошибки (не критические)
        $temporaryErrors = [
            'ItemServerUnavailable',
            'OfferLimitExceeded',
            'HTTP error 5', // 500+ ошибки
            'HTTP error 429', // Rate limit
            'cURL error', // Сетевые ошибки
            'Connection timed out'
        ];
        
        foreach ($temporaryErrors as $error) {
            if (stripos($message, $error) !== false) {
                return false;
            }
        }
        
        // По умолчанию считаем ошибку критической
        return true;
    }
}