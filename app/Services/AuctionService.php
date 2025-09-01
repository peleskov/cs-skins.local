<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Order;
use App\Http\Controllers\OrderController;
use App\Events\AuctionBidPlaced;
use Illuminate\Support\Facades\DB;
use Exception;

class AuctionService
{
    protected $orderController;

    public function __construct()
    {
        // OrderController будет создан через service container когда понадобится
    }
    
    protected function getOrderController(): OrderController
    {
        if (!$this->orderController) {
            $this->orderController = app(OrderController::class);
        }
        return $this->orderController;
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

        if (!$bidder->hasEnoughBalance($amount)) {
            throw new Exception('Недостаточно средств на балансе.');
        }

        return DB::transaction(function () use ($auction, $bidder, $amount) {
            // Возвращаем средства предыдущему лидеру
            if ($auction->last_bidder_id) {
                $previousBidder = Client::find($auction->last_bidder_id);
                $previousBidder->credit($auction->current_price);
            }

            // Холдируем средства нового участника
            $bidder->debit($amount);

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
            \Log::info('Broadcasting bid placed event', ['auction_id' => $auction->id, 'bid_id' => $bid->id]);
            broadcast(new AuctionBidPlaced($auction, $bid));

            return $bid;
        });
    }

    /**
     * Покупка по цене buyout (мгновенный выкуп)
     */
    public function buyout(Auction $auction, Client $buyer): Order
    {
        if (!$auction->is_active) {
            throw new Exception('Аукцион не активен.');
        }

        $buyoutPrice = $auction->buyout_price;
        if (!$buyoutPrice) {
            throw new Exception('Для этого аукциона не установлена цена мгновенного выкупа.');
        }

        return DB::transaction(function () use ($auction, $buyer, $buyoutPrice) {
            // Возвращаем средства текущему лидеру торгов
            if ($auction->last_bidder_id) {
                $currentLeader = Client::find($auction->last_bidder_id);
                $currentLeader->credit($auction->current_price);
            }

            // Завершаем аукцион
            $auction->update([
                'status' => Auction::STATUS_COMPLETED,
                'current_price' => $buyoutPrice,
            ]);

            // Создаем заказ через существующую систему
            $items = collect([[
                'listing_id' => $auction->listing_id,
                'item' => $auction->listing->toArray(),
                'price' => $buyoutPrice,
                'seller_id' => $auction->seller_id,
            ]]);

            return $this->orderController->createOrder($items, $buyer, $auction->seller);
        });
    }

    /**
     * Завершение аукциона по времени
     */
    public function completeAuction(Auction $auction): ?Order
    {
        if ($auction->status !== Auction::STATUS_ACTIVE || !$auction->is_ended) {
            throw new Exception('Аукцион не может быть завершен.');
        }

        return DB::transaction(function () use ($auction) {
            if ($auction->bid_count === 0) {
                // Нет ставок - отменяем аукцион
                $auction->update(['status' => Auction::STATUS_CANCELLED]);
                return null;
            }

            // Есть победитель
            $winner = Client::find($auction->last_bidder_id);
            
            // Завершаем аукцион
            $auction->update(['status' => Auction::STATUS_COMPLETED]);

            // Создаем заказ
            $items = collect([[
                'listing_id' => $auction->listing_id,
                'item' => $auction->listing->toArray(),
                'price' => $auction->current_price,
                'seller_id' => $auction->seller_id,
            ]]);

            return $this->orderController->createOrder($items, $winner, $auction->seller);
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