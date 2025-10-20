<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Auction;
use App\Models\Listing;
use App\Services\AuctionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class AuctionController extends Controller
{
    protected $auctionService;

    public function __construct(AuctionService $auctionService)
    {
        $this->auctionService = $auctionService;
    }

    /**
     * Публичная страница аукционов
     */
    public function index()
    {
        $featuredAuctions = Auction::with(['listing', 'seller', 'lastBidder'])
            ->active()
            ->orderBy('ends_at', 'asc')
            ->limit(24)
            ->get();
            
        // Добавляем статус корзины для каждого листинга (читаем из сессии)
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        
        // Добавляем статус избранного для каждого аукциона (через listing)
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $listingIds = $featuredAuctions->pluck('listing_id');
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', $listingIds)
                ->pluck('listing_id');
        }
        
        $currentUserId = auth('client')->id();
        
        $featuredAuctions->each(function ($auction) use ($cartItemIds, $favoriteItemIds, $currentUserId) {
            // Проверяем, является ли аукцион собственным
            $auction->is_own_auction = $currentUserId && $auction->seller_id === $currentUserId;
            
            // Добавляем статусы для листинга
            if ($auction->listing) {
                // Добавляем в корзину только если товар не собственный
                $auction->listing->is_in_cart = !$auction->is_own_auction && $cartItemIds->contains($auction->listing_id);
                $auction->listing->is_favorite = $favoriteItemIds->contains($auction->listing_id);
            }
        });
            
        $totalAuctions = Auction::active()->count();
        $hasMorePages = $totalAuctions > 24;
            
        return view('auctions.index', compact('featuredAuctions', 'totalAuctions', 'hasMorePages'));
    }

    /**
     * API для получения аукционов с пагинацией
     */
    public function getAuctions(Request $request): JsonResponse
    {
        $query = Auction::with(['listing', 'seller', 'lastBidder'])
            ->active();

        // Фильтр по listing_id
        if ($request->has('listing_id')) {
            $query->where('listing_id', $request->get('listing_id'));
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'ends_at');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['current_price', 'bid_count', 'ends_at', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 24);
        $auctions = $query->paginate($perPage);

        // Добавляем статус корзины и избранного для каждого аукциона
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $listingIds = collect($auctions->items())->pluck('listing_id');
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', $listingIds)
                ->pluck('listing_id');
        }
        
        $currentUserId = auth('client')->id();
        
        collect($auctions->items())->each(function ($auction) use ($cartItemIds, $favoriteItemIds, $currentUserId) {
            $auction->is_own_auction = $currentUserId && $auction->seller_id === $currentUserId;
            
            if ($auction->listing) {
                $auction->listing->is_in_cart = !$auction->is_own_auction && $cartItemIds->contains($auction->listing_id);
                $auction->listing->is_favorite = $favoriteItemIds->contains($auction->listing_id);
            }
        });

        return response()->json([
            'data' => $auctions->items(),
            'pagination' => [
                'current_page' => $auctions->currentPage(),
                'total' => $auctions->total(),
                'per_page' => $auctions->perPage(),
                'has_more_pages' => $auctions->hasMorePages(),
            ]
        ]);
    }

    /**
     * Детали аукциона
     */
    public function show(Auction $auction): JsonResponse
    {
        $auction->load([
            'listing',
            'seller',
            'lastBidder'
        ]);
        
        // Загружаем ставки отдельно
        $bids = $auction->bids()
            ->with('bidder')
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();
        
        $auction->setRelation('bids', $bids);

        return response()->json([
            'auction' => $auction,
            'can_bid' => Auth::check() ? $auction->canBid(Auth::user()) : false,
            'minimum_bid' => $auction->minimum_bid,
        ]);
    }

    /**
     * Создать аукцион
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'starting_price' => 'required|numeric|min:0.01',
            'duration_hours' => 'required|integer|min:1|max:168', // 1 час - 7 дней
            'min_bid_increment' => 'nullable|numeric|min:1',
        ]);

        try {
            $listing = Listing::findOrFail($request->listing_id);
            $client = Auth::user();

            $data = [
                'starting_price' => $request->starting_price,
            ];

            $auction = $this->auctionService->createAuction($listing, $client, $data);
            
            // Аукцион создается со статусом pending и требует ручной активации

            return response()->json([
                'success' => true,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Обновить аукцион
     */
    public function update(Request $request, Auction $auction): JsonResponse
    {
        $request->validate([
            'starting_price' => 'required|numeric|min:0.01',
            'min_bid_increment' => 'required|numeric|min:1',
            'duration_hours' => 'required|integer|min:1|max:168',
            'auto_extend' => 'boolean',
        ]);

        try {
            $client = Auth::user();

            // Проверяем права доступа
            if ($auction->seller_id !== $client->id) {
                throw new Exception('Вы не являетесь владельцем этого аукциона.');
            }

            // Проверяем, что аукцион можно редактировать
            if ($auction->status !== 'pending') {
                throw new Exception('Можно редактировать только аукционы в статусе "Черновик".');
            }

            // Обновляем данные аукциона
            $auction->update([
                'starting_price' => $request->starting_price,
                'current_price' => $request->starting_price, // Сбрасываем текущую цену
                'min_bid_increment' => $request->min_bid_increment,
                'duration_hours' => (int)$request->duration_hours,
                'auto_extend' => $request->auto_extend ?? false,
            ]);

            return response()->json([
                'success' => true,
                'auction' => $auction->fresh(['listing']),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Удалить аукцион
     */
    public function destroy(Auction $auction): JsonResponse
    {
        try {
            $client = Auth::user();

            // Проверяем права доступа
            if ($auction->seller_id !== $client->id) {
                throw new Exception('Вы не являетесь владельцем этого аукциона.');
            }

            // Проверяем, что аукцион можно удалить
            if ($auction->status !== 'pending') {
                throw new Exception('Можно удалять только аукционы в статусе "Черновик".');
            }

            // Удаляем аукцион
            $auction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Аукцион успешно удален',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Сделать ставку
     */
    public function bid(Request $request, Auction $auction): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $client = Auth::user();
            $bid = $this->auctionService->placeBid($auction, $client, $request->amount);

            return response()->json([
                'success' => true,
                'bid' => $bid,
                'auction' => $auction->fresh(['lastBidder']),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    /**
     * Отменить аукцион
     */
    public function cancel(Auction $auction): JsonResponse
    {
        try {
            $client = Auth::user();
            $this->auctionService->cancelAuction($auction, $client);

            return response()->json([
                'success' => true,
                'message' => 'Аукцион успешно отменен',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Мои аукционы (продавец)
     */
    public function myAuctions(): JsonResponse
    {
        $client = Auth::user();
        $auctions = $this->auctionService->getUserActiveAuctions($client);

        return response()->json($auctions);
    }

    /**
     * Мои ставки
     */
    public function myBids(): JsonResponse
    {
        $client = Auth::user();
        $bids = $this->auctionService->getUserBids($client);

        $grouped = [
            'leading' => [],
            'outbid' => [],
            'all' => $bids,
        ];

        foreach ($bids as $bid) {
            if ($bid->is_leading) {
                $grouped['leading'][] = $bid;
            } else {
                $grouped['outbid'][] = $bid;
            }
        }

        return response()->json($grouped);
    }

    /**
     * Выигранные аукционы
     */
    public function wonAuctions(): JsonResponse
    {
        $client = Auth::user();
        $auctions = $this->auctionService->getUserWonAuctions($client);

        return response()->json($auctions);
    }

    /**
     * История ставок для аукциона
     */
    public function bidHistory(Auction $auction): JsonResponse
    {
        $bids = $auction->bids()
            ->with('bidder:id,name,steam_avatar')
            ->orderBy('amount', 'desc')
            ->orderBy('placed_at', 'desc')
            ->paginate(20);

        return response()->json($bids);
    }

    /**
     * Мои аукционы
     */
    public function my(): JsonResponse
    {
        $client = Auth::user();
        
        $auctions = Auction::where('seller_id', $client->id)
            ->with(['listing', 'lastBidder', 'order'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auctions
        ]);
    }

    /**
     * Активировать аукцион
     */
    public function activate(Auction $auction): JsonResponse
    {
        try {
            $client = Auth::user();

            // Проверяем права доступа
            if ($auction->seller_id !== $client->id) {
                throw new Exception('Вы не являетесь владельцем этого аукциона.');
            }

            // Проверяем статус
            if ($auction->status !== 'pending') {
                throw new Exception('Можно активировать только аукционы в статусе "Черновик".');
            }

            // Активируем аукцион
            $this->auctionService->activateAuction($auction);

            return response()->json([
                'success' => true,
                'auction' => $auction->fresh(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Деактивировать аукцион (вернуть в черновик)
     */
    public function deactivate(Auction $auction): JsonResponse
    {
        try {
            $client = Auth::user();

            // Проверяем права доступа
            if ($auction->seller_id !== $client->id) {
                throw new Exception('Вы не являетесь владельцем этого аукциона.');
            }

            // Проверяем статус
            if ($auction->status !== 'active') {
                throw new Exception('Можно деактивировать только активные аукционы.');
            }

            // Деактивируем аукцион (проверка ставок внутри)
            $this->auctionService->deactivateAuction($auction);

            return response()->json([
                'success' => true,
                'auction' => $auction->fresh(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Проверка доступа к аукционам
     */
    public function checkAccess(): JsonResponse
    {
        $client = Auth::user();
        $hasAccess = $this->auctionService->canAccessAuctions($client);

        return response()->json([
            'has_access' => $hasAccess,
            'message' => $hasAccess 
                ? 'У вас есть доступ к аукционам' 
                : 'Для доступа к аукционам необходима минимум 1 покупка и 1 продажа',
        ]);
    }
}