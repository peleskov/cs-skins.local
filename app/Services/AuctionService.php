<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Transaction;
use App\Http\Controllers\OrderController;
use App\Events\AuctionBidPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AuctionService
{
    public function __construct()
    {
        // Сервис готов к использованию
    }

    /**
     * Проверка доступа пользователя к аукционам
     */
    public function canAccessAuctions(Client $client): bool
    {
        $hasPurchase = Order::where('buyer_id', $client->id)
            ->where('status', 'completed')
            ->exists();

        $hasSale = Order::where('seller_id', $client->id)
            ->where('status', 'completed')
            ->exists();

        return $hasPurchase && $hasSale;
    }

    /**
     * Создание нового аукциона
     */
    public function createAuction(Listing $listing, Client $seller, array $data): Auction
    {
        if ($seller->isPurchasesBlocked()) {
            throw new Exception($seller->getPurchasesBlockReasonForUser() ?: 'Операции с предметами заблокированы');
        }

        // Временно закомментировано для тестирования
        // if (!$this->canAccessAuctions($seller)) {
        //     throw new Exception('У вас нет доступа к аукционам. Необходима минимум 1 покупка и 1 продажа.');
        // }

        if ($listing->seller_id !== $seller->id) {
            throw new Exception('Вы не являетесь владельцем этого листинга.');
        }

        if (!$listing->isActive()) {
            throw new Exception('Листинг недоступен для создания аукциона.');
        }

        if ($listing->isOnAuction()) {
            throw new Exception('Аукцион для этого предмета уже существует. Вы можете редактировать его в разделе "Мои аукционы".');
        }

        return DB::transaction(function () use ($listing, $seller, $data) {
            $auction = Auction::create([
                'seller_id' => $seller->id,
                'listing_id' => $listing->id,
                'starting_price' => $data['starting_price'],
                'current_price' => $data['starting_price'],
                'min_bid_increment' => 1,
                'status' => Auction::STATUS_PENDING,
                'starts_at' => null,
                'ends_at' => null,
                'bid_count' => 0,
                'last_bidder_id' => null,
                'duration_hours' => 24,
            ]);

            return $auction;
        });
    }

    /**
     * Активация аукциона
     */
    public function activateAuction(Auction $auction): void
    {
        if ($auction->status !== Auction::STATUS_PENDING) {
            throw new Exception('Аукцион не может быть активирован.');
        }

        $now = now();
        $auction->update([
            'status' => Auction::STATUS_ACTIVE,
            'starts_at' => $now,
            'ends_at' => $now->addHours($auction->duration_hours),
        ]);
    }

    /**
     * Деактивация аукциона (возврат в черновик)
     */
    public function deactivateAuction(Auction $auction): void
    {
        if ($auction->status !== Auction::STATUS_ACTIVE) {
            throw new Exception('Можно деактивировать только активные аукционы.');
        }

        DB::transaction(function () use ($auction) {
            // Проверяем количество ставок в транзакции для безопасности
            if ($auction->bid_count > 0) {
                throw new Exception('Нельзя деактивировать аукцион с активными ставками.');
            }

            // Возвращаем в статус черновика
            $auction->update([
                'status' => Auction::STATUS_PENDING,
                'starts_at' => null,
                'ends_at' => null,
            ]);
        });
    }

    /**
     * Размещение ставки
     */
    public function placeBid(Auction $auction, Client $bidder, float $amount): AuctionBid
    {
        // Временно отключено для тестирования
        // if (!$this->canAccessAuctions($bidder)) {
        //     throw new Exception('У вас нет доступа к аукционам.');
        // }

        if ($bidder->isBalanceBlocked()) {
            throw new Exception($bidder->getBalanceBlockReasonForUser() ?: 'Операции с балансом заблокированы');
        }

        // Проверяем отдельные причины почему нельзя делать ставку
        if ($auction->seller_id === $bidder->id) {
            throw new Exception('Вы не можете делать ставки на собственный аукцион.');
        }

        if (!$auction->is_active) {
            throw new Exception('Аукцион неактивен.');
        }

        // Специальная обработка для двух ставок подряд - это предупреждение, а не ошибка
        if ($auction->last_bidder_id === $bidder->id) {
            throw new Exception('Нельзя сделать две ставки подряд. Дождитесь ставки других участников.');
        }

        if ($amount < $auction->minimum_bid) {
            throw new Exception('Ставка должна быть не менее ' . $auction->minimum_bid . ' руб.');
        }

        return DB::transaction(function () use ($auction, $bidder, $amount) {
            // Сначала пытаемся списать средства атомарно
            if (!$bidder->debit($amount)) {
                throw new Exception('Недостаточно средств на балансе.');
            }

            // Создаем транзакцию для ставки
            Transaction::create([
                'client_id' => $bidder->id,
                'order_id' => null,
                'type' => Transaction::TYPE_AUCTION_BID,
                'amount' => $amount, // Положительная сумма, направление определяется по типу
                'status' => Transaction::STATUS_COMPLETED,
                'description' => 'Ставка на аукцион #' . $auction->id . ' - ' . $auction->listing->market_hash_name,
                'metadata' => [
                    'auction_id' => $auction->id,
                    'listing_id' => $auction->listing_id,
                ],
            ]);

            // Если списание прошло успешно, возвращаем средства предыдущему лидеру
            if ($auction->last_bidder_id) {
                $previousBidder = Client::find($auction->last_bidder_id);
                $previousBidder->credit($auction->current_price);

                // Создаем транзакцию для возврата средств предыдущему лидеру
                Transaction::create([
                    'client_id' => $previousBidder->id,
                    'order_id' => null,
                    'type' => Transaction::TYPE_AUCTION_REFUND,
                    'amount' => $auction->current_price,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => 'Возврат средств за перебитую ставку в аукционе #' . $auction->id,
                    'metadata' => [
                        'auction_id' => $auction->id,
                        'listing_id' => $auction->listing_id,
                        'reason' => 'outbid',
                    ],
                ]);
            }

            // Создаем ставку
            $bid = AuctionBid::create([
                'auction_id' => $auction->id,
                'bidder_id' => $bidder->id,
                'amount' => $amount,
            ]);

            // Обновляем аукцион
            $auction->update([
                'current_price' => $amount,
                'bid_count' => $auction->bid_count + 1,
                'last_bidder_id' => $bidder->id,
            ]);

            // Автопродление при поздней ставке
            if ($auction->shouldExtend()) {
                $auction->extend();
            }

            // Загружаем отношения для broadcast
            $bid->load('bidder');
            $auction->load('lastBidder');

            // Отправляем событие через WebSocket
            Log::info('Broadcasting bid placed event', ['auction_id' => $auction->id, 'bid_id' => $bid->id]);
            broadcast(new AuctionBidPlaced($auction, $bid));

            return $bid;
        });
    }


    /**
     * Завершение аукциона по времени
     */
    public function completeAuction(Auction $auction): ?Order
    {
        // Проверки входных условий
        if ($auction->status !== Auction::STATUS_ACTIVE) {
            throw new Exception('Аукцион не активен');
        }
        
        if ($auction->ends_at > now()) {
            throw new Exception('Аукцион еще не закончился');
        }

        return DB::transaction(function () use ($auction) {
            // Сценарий 1: Нет ставок
            if ($auction->bid_count === 0) {
                $auction->update(['status' => Auction::STATUS_CANCELLED]);
                // С листингом ничего не делаем - остается активным
                return null;
            }

            // Сценарий 2: Есть победитель
            $winner = Client::find($auction->last_bidder_id);
            $seller = $auction->seller;
            
            // Завершаем аукцион
            $auction->update(['status' => Auction::STATUS_COMPLETED]);
            
            try {
                // Создаем заказ через метод auctionBuy в OrderController
                $orderController = app(\App\Http\Controllers\OrderController::class);
                $order = $orderController->auctionBuy($auction, $winner, $seller);
                
                // Связываем аукцион с заказом
                if ($order && isset($order['id'])) {
                    $auction->update(['order_id' => $order['id']]);
                    
                    // Возвращаем объект Order
                    return \App\Models\Order::find($order['id']);
                }
                
                throw new Exception('Заказ не был создан');
                
            } catch (Exception $e) {
                // Заказ не создался, но аукцион остается завершенным
                // Возвращаем средства победителю
                $winner->credit($auction->current_price);

                // Создаем транзакцию для возврата средств при ошибке создания заказа
                Transaction::create([
                    'client_id' => $winner->id,
                    'order_id' => null,
                    'type' => Transaction::TYPE_AUCTION_REFUND,
                    'amount' => $auction->current_price,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => 'Возврат средств из-за ошибки создания заказа для аукциона #' . $auction->id,
                    'metadata' => [
                        'auction_id' => $auction->id,
                        'listing_id' => $auction->listing_id,
                        'reason' => 'order_creation_failed',
                        'error' => $e->getMessage(),
                    ],
                ]);

                // Логируем проблему
                Log::error('Failed to create order for completed auction', [
                    'auction_id' => $auction->id,
                    'winner_id' => $winner->id,
                    'amount' => $auction->current_price,
                    'error' => $e->getMessage()
                ]);

                // order_id остается NULL
                return null;
            }
        });
    }

    /**
     * Отмена аукциона продавцом
     */
    public function cancelAuction(Auction $auction, Client $seller): void
    {
        if ($auction->seller_id !== $seller->id) {
            throw new Exception('Вы не являетесь владельцем этого аукциона.');
        }

        if ($auction->status !== Auction::STATUS_PENDING && $auction->bid_count > 0) {
            throw new Exception('Нельзя отменить аукцион после размещения ставок.');
        }

        DB::transaction(function () use ($auction) {
            // Возвращаем средства если были ставки
            if ($auction->last_bidder_id && $auction->bid_count > 0) {
                $bidder = Client::find($auction->last_bidder_id);
                $bidder->credit($auction->current_price);

                // Создаем транзакцию для возврата средств при отмене аукциона
                Transaction::create([
                    'client_id' => $bidder->id,
                    'order_id' => null,
                    'type' => Transaction::TYPE_AUCTION_REFUND,
                    'amount' => $auction->current_price,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => 'Возврат средств за отмененный аукцион #' . $auction->id,
                    'metadata' => [
                        'auction_id' => $auction->id,
                        'listing_id' => $auction->listing_id,
                        'reason' => 'cancelled',
                    ],
                ]);
            }

            $auction->update(['status' => Auction::STATUS_CANCELLED]);
        });
    }

    /**
     * Получение активных аукционов пользователя
     */
    public function getUserActiveAuctions(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        return Auction::where('seller_id', $client->id)
            ->whereIn('status', [Auction::STATUS_PENDING, Auction::STATUS_ACTIVE])
            ->with(['listing', 'bids'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Получение ставок пользователя
     */
    public function getUserBids(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        return AuctionBid::where('bidder_id', $client->id)
            ->with(['auction' => function($q) {
                $q->with('listing');
            }])
            ->orderBy('placed_at', 'desc')
            ->get();
    }

    /**
     * Получение выигранных аукционов
     */
    public function getUserWonAuctions(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        return Auction::where('last_bidder_id', $client->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->with('listing')
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}