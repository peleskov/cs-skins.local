<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CaseInventoryItem;
use App\Models\VirtualItem;
use App\Models\Upgrade;
use App\Services\UpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Exception;

class UpgradeController extends Controller
{
    public function __construct(
        protected UpgradeService $upgradeService
    ) {}

    /**
     * Страница апгрейда
     */
    public function index(): View
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        // Получаем доступные предметы из инвентаря кейсов
        $inventoryItems = CaseInventoryItem::where('client_id', $client->id)
            ->where('status', 'available')
            ->with('virtualItem')
            ->orderByDesc('price')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->virtualItem->name,
                    'price' => (float) $item->price,
                    'image_url' => $item->virtualItem->image_url,
                    'rarity' => $item->virtualItem->rarity,
                    'rarity_color' => $item->virtualItem->rarity_color,
                    'quality' => $item->virtualItem->quality,
                    'weapon_type' => $item->virtualItem->weapon_type,
                ];
            });

        // Данные пользователя
        $userData = [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => $client->steam_avatar,
            'balance' => (float) $client->balance,
            'bonus_balance' => (float) $client->bonus_balance,
        ];

        // Настройки апгрейда из сервиса
        $settings = $this->upgradeService->getSettings();

        return view('cases.upgrade', [
            'inventoryItems' => $inventoryItems,
            'userData' => $userData,
            'settings' => $settings,
        ]);
    }

    /**
     * API: Расчет шанса
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'bet_total' => 'required|numeric|min:0.01',
            'target_price' => 'required|numeric|min:0.01',
        ]);

        $betTotal = (float) $request->input('bet_total');
        $targetPrice = (float) $request->input('target_price');

        $chance = $this->upgradeService->calculateChance($betTotal, $targetPrice);

        return response()->json([
            'success' => true,
            'chance' => round($chance, 2),
            'bet_total' => $betTotal,
            'target_price' => $targetPrice,
        ]);
    }

    /**
     * API: Получить доступные целевые предметы
     */
    public function getTargets(Request $request): JsonResponse
    {
        $request->validate([
            'bet_total' => 'required|numeric|min:0.01',
        ]);

        $betTotal = (float) $request->input('bet_total');
        $targets = $this->upgradeService->getAvailableTargets($betTotal);
        $priceRange = $this->upgradeService->getPriceRange($betTotal);

        return response()->json([
            'success' => true,
            'targets' => $targets,
            'price_range' => $priceRange,
        ]);
    }

    /**
     * API: История апгрейдов
     */
    public function history(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $upgrades = Upgrade::where('client_id', $client->id)
            ->with(['targetVirtualItem'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($upgrade) {
                // Получаем данные о ставочных предметах
                $betItems = [];
                if (!empty($upgrade->bet_items)) {
                    $itemIds = array_column($upgrade->bet_items, 'item_id');
                    $inventoryItems = CaseInventoryItem::whereIn('id', $itemIds)
                        ->with('virtualItem')
                        ->get()
                        ->keyBy('id');

                    foreach ($upgrade->bet_items as $betItem) {
                        $invItem = $inventoryItems->get($betItem['item_id']);
                        if ($invItem && $invItem->virtualItem) {
                            $betItems[] = [
                                'id' => $invItem->id,
                                'name' => $invItem->virtualItem->name,
                                'price' => (float) $betItem['price'],
                                'image_url' => $invItem->virtualItem->image_url,
                                'rarity' => $invItem->virtualItem->rarity,
                            ];
                        }
                    }
                }

                return [
                    'id' => $upgrade->id,
                    'chance' => (float) $upgrade->win_chance,
                    'result' => $upgrade->result,
                    'is_win' => $upgrade->isWin(),
                    'bet_items' => $betItems,
                    'bet_balance' => (float) $upgrade->bet_balance,
                    'total_bet' => (float) $upgrade->total_bet,
                    'target' => [
                        'name' => $upgrade->targetVirtualItem->name,
                        'price' => (float) $upgrade->target_price,
                        'image_url' => $upgrade->targetVirtualItem->image_url,
                        'rarity' => $upgrade->targetVirtualItem->rarity,
                    ],
                    'created_at' => $upgrade->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $upgrades,
        ]);
    }

    /**
     * API: Выполнить апгрейд
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'nullable|array|max:4',
            'item_ids.*' => 'integer|exists:case_inventory_items,id',
            'balance_amount' => 'nullable|numeric|min:0',
            'target_id' => 'required|integer|exists:virtual_items,id',
        ]);

        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $itemIds = $request->input('item_ids', []);
        $balanceAmount = (float) $request->input('balance_amount', 0);
        $targetId = (int) $request->input('target_id');

        // Проверка что есть хоть какая-то ставка
        if (empty($itemIds) && $balanceAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Выберите предметы или добавьте баланс',
            ], 422);
        }

        try {
            $upgrade = $this->upgradeService->execute($client, $itemIds, $balanceAmount, $targetId);

            // Формируем ответ
            $response = [
                'success' => true,
                'result' => $upgrade->result,
                'is_win' => $upgrade->isWin(),
                'chance' => (float) $upgrade->win_chance,
                'roll_value' => (float) $upgrade->roll_value,
                'balance' => (float) $client->fresh()->balance,
            ];

            // Если выиграл - добавляем информацию о выигранном предмете
            if ($upgrade->isWin() && $upgrade->wonItem) {
                $response['won_item'] = [
                    'id' => $upgrade->wonItem->id,
                    'name' => $upgrade->targetVirtualItem->name,
                    'price' => (float) $upgrade->target_price,
                    'image_url' => $upgrade->targetVirtualItem->image_url,
                    'rarity' => $upgrade->targetVirtualItem->rarity,
                    'quality' => $upgrade->targetVirtualItem->quality,
                ];
            }

            return response()->json($response);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
