<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\CaseModel;
use App\Models\CaseTier;
use App\Models\Client;
use App\Services\CaseService;
use \App\Http\Controllers\OrderController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class CaseController extends Controller
{
    /**
     * Страница списка кейсов
     */
    public function index(): View
    {
        $cases = CaseModel::active()
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'price',
                'image_url',
                'accumulated_fund',
                'category_id'
            ])
            ->with('category:id,name,sort_order')
            ->whereHas('tiers', function ($query) {
                // Находим минимальную цену среди уровней и проверяем что есть предметы
                $query->whereRaw('price = (SELECT MIN(price) FROM case_tiers WHERE case_id = cases.id)')
                    ->whereHas('items');
            })
            ->get();

        return view('cases.index', compact('cases'));
    }

    /**
     * Страница детального просмотра кейса
     */
    public function show(string $slug): View
    {
        $case = CaseModel::where('slug', $slug)
            ->where('is_active', true)
            ->with(['tiers' => function ($query) {
                $query->orderBy('price', 'asc')
                    ->with(['items.inventoryItem' => function ($q) {
                        $q->select([
                            'id',
                            'item_name',
                            'float_value',
                            'float_min',
                            'float_max',
                            'icon_url',
                            'market_hash_name'
                        ]);
                    }]);
            }])
            ->firstOrFail();

        // Форматируем данные для передачи в компонент
        $caseData = [
            'id' => $case->id,
            'name' => $case->name,
            'slug' => $case->slug,
            'description' => $case->description,
            'price' => (float) $case->price,
            'image_url' => $case->image_url,
            'accumulated_fund' => (float) $case->accumulated_fund,
            'tiers' => $case->tiers->map(function ($tier) {
                return [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'price' => (float) $tier->price,
                    'probability' => (float) $tier->probability,
                    'items' => $tier->items->map(function ($item) use ($tier) {
                        $inventory = $item->inventoryItem;
                        return [
                            'id' => $inventory->id,
                            'name' => $inventory->item_name,
                            'float_value' => $inventory->float_value,
                            'float_min' => $inventory->float_min,
                            'float_max' => $inventory->float_max,
                            'price' => $inventory->getCurrentPrice() ?: 0,
                            'image_url' => $inventory->icon_url,
                            'tier_id' => $tier->id,
                            'structured_tags' => $inventory->structured_tags
                        ];
                    })
                ];
            })
        ];

        return view('cases.show', compact('case', 'caseData'));
    }

    /**
     * API: Получить список кейсов
     */
    public function list(): JsonResponse
    {
        $cases = CaseModel::active()
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'price',
                'image_url',
                'accumulated_fund',
                'category_id'
            ])
            ->with('category:id,name,sort_order')
            ->get();

        return response()->json([
            'data' => $cases
        ]);
    }

    /**
     * API: Получить детали кейса
     */
    public function detail(string $slug): JsonResponse
    {
        $case = CaseModel::where('slug', $slug)
            ->where('is_active', true)
            ->with(['tiers' => function ($query) {
                $query->orderBy('price', 'asc')
                    ->with(['items.inventoryItem']);
            }])
            ->firstOrFail();

        $tiers = $case->tiers->map(function ($tier) {
            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'price' => $tier->price,
                'probability' => $tier->probability,
                'items' => $tier->items->map(function ($item) {
                    $inventory = $item->inventoryItem;
                    return [
                        'id' => $inventory->id,
                        'name' => $inventory->item_name,
                        'float_value' => $inventory->float_value,
                        'float_min' => $inventory->float_min,
                        'float_max' => $inventory->float_max,
                        'price' => $inventory->getCurrentPrice() ?: 0,
                        'image_url' => $inventory->icon_url,
                        'structured_tags' => $inventory->structured_tags
                    ];
                })
            ];
        });

        return response()->json([
            'data' => [
                'id' => $case->id,
                'name' => $case->name,
                'slug' => $case->slug,
                'description' => $case->description,
                'price' => $case->price,
                'image_url' => $case->image_url,
                'accumulated_fund' => $case->accumulated_fund,
                'tiers' => $tiers
            ]
        ]);
    }

    /**
     * API: Покупка и открытие кейса
     */
    public function purchaseCase(Request $request): JsonResponse
    {
        $request->validate([
            'case_id' => 'required|exists:cases,id',
        ]);

        try {
            $case = CaseModel::findOrFail($request->case_id);
            /** @var Client $buyer */
            $buyer = Auth::guard('client')->user();

            if (!$buyer) {
                throw new Exception('Необходима авторизация');
            }
            
            // Проверяем что покупатель не бот
            if ($buyer->is_bot) {
                throw new Exception('Вы не можете покупать кейсы');
            }

            // Проверяем что кейс активен
            if (!$case->is_active) {
                throw new Exception('Кейс недоступен для покупки');
            }
            
            // Проверяем что у кейса есть предметы
            if (!$case->tiers()->whereHas('items')->exists()) {
                throw new Exception('В кейсе нет доступных предметов');
            }
            
            // Используем транзакцию для атомарности всей операции
            return DB::transaction(function () use ($case, $buyer) {
                // Проверяем баланс и списываем средства
                if (!$buyer->debit($case->price)) {
                    throw new Exception('Недостаточно средств на балансе');
                }
                
                $caseService = app(CaseService::class);

                // Пополняем фонд кейса (используем fund_percent)
                $fundAmount = $case->price * ($case->fund_percent / 100);
                $case->increment('accumulated_fund', $fundAmount);

                // Создаем транзакцию покупки кейса (видна покупателю)
                Transaction::create([
                    'client_id' => $buyer->id,
                    'type' => Transaction::TYPE_PURCHASE,
                    'amount' => $case->price,
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => "Покупка кейса \"{$case->name}\"",
                    'metadata' => [
                        'case_id' => $case->id,
                        'case_name' => $case->name,
                        'fund_amount' => $fundAmount,
                        'site_revenue' => $case->price - $fundAmount
                    ]
                ]);

                // Создаем транзакцию дохода сайта (скрыта от покупателя, видна только в админке)
                $siteRevenue = $case->price - $fundAmount;
                if ($siteRevenue > 0) {
                    // Получаем системного клиента
                    $systemClient = Client::where('email', 'system@cs-skins.local')->first();

                    Transaction::create([
                        'client_id' => $systemClient?->id, // Системная транзакция
                        'type' => Transaction::TYPE_FEE,
                        'amount' => $siteRevenue,
                        'status' => Transaction::STATUS_COMPLETED,
                        'description' => "Доход с продажи кейса \"{$case->name}\"",
                        'metadata' => [
                            'case_id' => $case->id,
                            'case_name' => $case->name,
                            'buyer_id' => $buyer->id,
                            'case_price' => $case->price,
                            'fund_percent' => $case->fund_percent,
                            'source' => 'case_revenue'
                        ]
                    ]);
                }

                // Открываем кейс и получаем приз
                $prizeItem = $caseService->openCase($case, $buyer);

                // Создаем заказ для приза через OrderController
                $orderController = app(OrderController::class);
                $order = $orderController->casePrizeBuy($case, $prizeItem, $buyer);

                return response()->json([
                    'success' => true,
                    'prize' => [
                        'id' => $prizeItem->id
                    ]
                ]);
            });

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}