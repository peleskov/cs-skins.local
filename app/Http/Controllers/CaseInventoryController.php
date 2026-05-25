<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CaseOpen;
use App\Models\CaseInventoryItem;
use App\Services\CaseInventoryService;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CaseInventoryController extends Controller
{
    public function __construct(
        private CaseInventoryService $caseInventoryService,
        private WithdrawService $withdrawService
    ) {}

    /**
     * Страница инвентаря кейсов
     */
    public function index(): View
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $paginator = $this->caseInventoryService->getItemsPaginated($client, null, 25, 1);
        $items = $paginator->getCollection();

        $counts = $this->caseInventoryService->getCountsByStatus($client);

        // Форматируем данные для Vue компонента
        $inventoryData = $items->map(function ($item) {
            $virtual = $item->virtualItem;
            return [
                'id' => $item->id,
                'name' => $virtual->name,
                'price' => (float) $item->price,
                'image_url' => $virtual->image_url,
                'rarity' => $virtual->rarity,
                'rarity_color' => $virtual->rarity_color,
                'quality' => $virtual->quality,
                'weapon_type' => $virtual->weapon_type,
                'status' => $item->status,
                'source_type' => $item->source_type,
                'is_anti_unluck' => $item->is_anti_unluck,
                'created_at' => $item->created_at->toISOString(),
            ];
        });

        // Данные пользователя
        $userData = [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => $client->steam_avatar,
            'steam_id' => $client->steam_id,
            'balance' => (float) $client->balance,
            'bonus_balance' => (float) $client->bonus_balance,
            'trade_url' => $client->steam_trade_url,
            'avatar_border_color' => $client->avatar_border_color,
            'nickname_color' => $client->nickname_color,
            'is_premium' => $client->isPremium(),
            'is_withdraw_blocked' => $client->isWithdrawBlocked(),
            'withdraw_block_reason' => $client->getWithdrawBlockReasonForUser(),
        ];

        // Любимый кейс (самый часто открываемый)
        $favoriteCase = $this->getFavoriteCase($client);

        // Лучший предмет (самый дорогой выпавший)
        $bestItem = $this->getBestItem($client);

        return view('cases.inventory', [
            'inventoryData' => $inventoryData,
            'inventoryPagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'inventoryCounts' => $counts,
            'userData' => $userData,
            'favoriteCase' => $favoriteCase,
            'bestItem' => $bestItem,
        ]);
    }

    /**
     * Получить любимый кейс (самый часто открываемый)
     */
    private function getFavoriteCase(Client $client): ?array
    {
        $favorite = CaseOpen::where('client_id', $client->id)
            ->select('case_id', DB::raw('COUNT(*) as opens_count'))
            ->groupBy('case_id')
            ->orderByDesc('opens_count')
            ->with('case')
            ->first();

        if (!$favorite || !$favorite->case) {
            return null;
        }

        return [
            'id' => $favorite->case->id,
            'name' => $favorite->case->name,
            'slug' => $favorite->case->slug,
            'image_url' => $favorite->case->image_url,
            'opens_count' => $favorite->opens_count,
        ];
    }

    /**
     * Получить лучший предмет (самый дорогой)
     */
    private function getBestItem(Client $client): ?array
    {
        $best = CaseInventoryItem::where('client_id', $client->id)
            ->orderByDesc('price')
            ->with('virtualItem')
            ->first();

        if (!$best || !$best->virtualItem) {
            return null;
        }

        return [
            'id' => $best->id,
            'name' => $best->virtualItem->name,
            'price' => (float) $best->price,
            'image_url' => $best->virtualItem->image_url,
            'rarity' => $best->virtualItem->rarity,
            'rarity_color' => $best->virtualItem->rarity_color,
        ];
    }

    /**
     * API: Получить инвентарь пользователя
     */
    public function getItems(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [25, 50, 100])) $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $status = $request->boolean('only_available') ? CaseInventoryItem::STATUS_AVAILABLE : null;

        $paginator = $this->caseInventoryService->getItemsPaginated($client, $status, $perPage, $page);

        $data = $paginator->getCollection()->map(function ($item) {
            $virtual = $item->virtualItem;
            return [
                'id' => $item->id,
                'name' => $virtual->name,
                'price' => (float) $item->price,
                'image_url' => $virtual->image_url,
                'rarity' => $virtual->rarity,
                'rarity_color' => $virtual->rarity_color,
                'quality' => $virtual->quality,
                'weapon_type' => $virtual->weapon_type,
                'status' => $item->status,
                'source_type' => $item->source_type,
                'is_anti_unluck' => $item->is_anti_unluck,
                'created_at' => $item->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'counts' => $this->caseInventoryService->getCountsByStatus($client),
        ]);
    }

    /**
     * API: Продать предметы
     */
    public function sell(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'required_without:all|array',
            'item_ids.*' => 'integer',
            'all' => 'required_without:item_ids|boolean',
        ]);

        /** @var Client $client */
        $client = Auth::guard('client')->user();

        if ($client->isBalanceBlocked()) {
            return response()->json([
                'success' => false,
                'message' => $client->getBalanceBlockReasonForUser() ?: 'Операции с балансом заблокированы',
            ], 403);
        }
        if ($client->isPurchasesBlocked()) {
            return response()->json([
                'success' => false,
                'message' => $client->getPurchasesBlockReasonForUser() ?: 'Операции с предметами заблокированы',
            ], 403);
        }

        try {
            if ($request->boolean('all')) {
                $result = $this->caseInventoryService->sellAllItems($client);
            } else {
                $result = $this->caseInventoryService->sellItems($client, $request->input('item_ids'));
            }

            return response()->json([
                'success' => true,
                'sold_count' => $result['sold_count'],
                'total_amount' => $result['total_amount'],
                'sold_ids' => $result['sold_ids'],
                'balance' => (float) $client->fresh()->balance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Получить замены для предмета
     */
    public function getReplacements(Request $request, int $id): JsonResponse
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $item = CaseInventoryItem::where('id', $id)
            ->where('client_id', $client->id)
            ->with('virtualItem')
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Предмет не найден',
            ], 404);
        }

        $search = $request->input('search');
        $replacements = $this->withdrawService->findReplacements($item, $search);
        $priceRange = $this->withdrawService->getPriceRange((float) $item->price);

        return response()->json([
            'success' => true,
            'original_item' => [
                'id' => $item->id,
                'name' => $item->virtualItem->name,
                'price' => (float) $item->price,
                'image_url' => $item->virtualItem->image_url,
            ],
            'price_range' => $priceRange,
            'replacements' => $replacements->map(function ($listing) {
                // Получаем rarity из structured_tags
                $rarityTag = collect($listing->structured_tags)->firstWhere('category_code', 'rarity');
                $rarity = $rarityTag['normalized_value'] ?? null;

                return [
                    'id' => $listing->id,
                    'name' => $listing->inventory_item_name,
                    'market_hash_name' => $listing->market_hash_name,
                    'price' => (float) $listing->price,
                    'image_url' => $listing->inventory_icon_url
                        ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url
                        : null,
                    'wear_name' => $listing->wear_name,
                    'float_value' => $listing->float_value,
                    'is_stattrak' => $listing->is_stattrak,
                    'is_souvenir' => $listing->is_souvenir,
                    'rarity' => $rarity,
                ];
            }),
        ]);
    }

    /**
     * API: Вывести предмет
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'listing_id' => 'nullable|integer|exists:listings,id',
        ]);

        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $item = CaseInventoryItem::where('id', $id)
            ->where('client_id', $client->id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Предмет не найден',
            ], 404);
        }

        try {
            $result = $this->withdrawService->withdraw(
                $item,
                $client,
                $request->input('listing_id')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
