<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\CaseOpen;
use App\Models\Client;
use App\Services\CaseService;
use App\Services\FreeCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
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
                'category_id',
                'case_type',
                'label_hot',
                'label_new',
                'label_limited',
                'label_free',
            ])
            ->with('category:id,name,icon,sort_order')
            ->whereHas('tiers', function ($query) {
                $query->whereHas('items');
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
                    ->with(['items.virtualItem']);
            }])
            ->firstOrFail();

        /** @var Client|null $client */
        $client = Auth::guard('client')->user();
        $caseService = app(CaseService::class);

        // Формируем данные для передачи в компонент
        $caseData = [
            'id' => $case->id,
            'name' => $case->name,
            'slug' => $case->slug,
            'description' => $case->description,
            'price' => (float) $case->price,
            'image_url' => $case->image_url,
            'case_type' => $case->case_type,
            'tiers' => $case->tiers->map(function ($tier) {
                return [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'price' => (float) $tier->price,
                    'probability' => (float) $tier->probability,
                    'items' => $tier->items->map(function ($item) use ($tier) {
                        $virtual = $item->virtualItem;
                        return [
                            'id' => $item->id,
                            'name' => $virtual->name,
                            'price' => (float) $item->price,
                            'image_url' => $virtual->image_url,
                            'tier_id' => $tier->id,
                            'rarity' => $virtual->rarity,
                            'rarity_color' => $virtual->rarity_color,
                            'quality' => $virtual->quality,
                            'weapon_type' => $virtual->weapon_type,
                        ];
                    })
                ];
            }),
            'multipliers' => [
                'available' => $client
                    ? $caseService->getAvailableMultipliers($case, $client)
                    : [1, 2, 3, 4, 5, 10],
                'max_opens' => $client
                    ? $caseService->getMaxOpens($case, $client)
                    : 10,
            ],
        ];

        // Для бесплатных кейсов добавляем информацию о бесплатных открытиях
        if ($case->isFree() && $client) {
            $freeCaseService = app(FreeCaseService::class);
            $caseData['free_opens_info'] = $freeCaseService->getFreeOpensInfo($client, $case);
        }

        // Для лимитированных кейсов
        if ($case->isLimited()) {
            $caseData['available_until'] = $case->available_until?->toIso8601String();
            $caseData['total_opens_limit'] = $case->total_opens_limit;
            $caseData['total_opens_count'] = $case->total_opens_count;
        }

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
                'category_id',
                'case_type',
                'label_hot',
                'label_new',
                'label_limited',
                'label_free',
            ])
            ->with('category:id,name,icon,sort_order')
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
                    ->with(['items.virtualItem']);
            }])
            ->firstOrFail();

        /** @var Client|null $client */
        $client = Auth::guard('client')->user();
        $caseService = app(CaseService::class);

        $tiers = $case->tiers->map(function ($tier) {
            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'price' => (float) $tier->price,
                'probability' => (float) $tier->probability,
                'items' => $tier->items->map(function ($item) use ($tier) {
                    $virtual = $item->virtualItem;
                    return [
                        'id' => $item->id,
                        'name' => $virtual->name,
                        'price' => (float) $item->price,
                        'image_url' => $virtual->image_url,
                        'tier_id' => $tier->id,
                        'rarity' => $virtual->rarity,
                        'rarity_color' => $virtual->rarity_color,
                        'quality' => $virtual->quality,
                        'weapon_type' => $virtual->weapon_type,
                    ];
                })
            ];
        });

        $data = [
            'id' => $case->id,
            'name' => $case->name,
            'slug' => $case->slug,
            'description' => $case->description,
            'price' => (float) $case->price,
            'image_url' => $case->image_url,
            'case_type' => $case->case_type,
            'tiers' => $tiers,
            'multipliers' => [
                'available' => $client
                    ? $caseService->getAvailableMultipliers($case, $client)
                    : [1, 2, 3, 4, 5, 10],
                'max_opens' => $client
                    ? $caseService->getMaxOpens($case, $client)
                    : 10,
            ],
        ];

        // Для бесплатных кейсов добавляем информацию о бесплатных открытиях
        if ($case->isFree() && $client) {
            $freeCaseService = app(FreeCaseService::class);
            $data['free_opens_info'] = $freeCaseService->getFreeOpensInfo($client, $case);
        }

        // Для лимитированных кейсов
        if ($case->isLimited()) {
            $data['available_until'] = $case->available_until?->toIso8601String();
            $data['total_opens_limit'] = $case->total_opens_limit;
            $data['total_opens_count'] = $case->total_opens_count;
        }

        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * API: Покупка и открытие кейса
     */
    public function purchaseCase(Request $request): JsonResponse
    {
        $request->validate([
            'case_id' => 'required|exists:cases,id',
            'count' => 'required|integer|min:1|max:10',
        ]);

        try {
            $case = CaseModel::findOrFail($request->case_id);
            /** @var Client $client */
            $client = Auth::guard('client')->user();
            $count = $request->input('count', 1);

            if (!$client) {
                throw new Exception('Необходима авторизация');
            }

            // Проверяем что покупатель не бот
            if ($client->is_bot) {
                throw new Exception('Боты не могут открывать кейсы');
            }

            // Проверяем что count допустим
            $allowedMultipliers = [1, 2, 3, 4, 5, 10];
            if (!in_array($count, $allowedMultipliers)) {
                throw new Exception('Недопустимое количество');
            }

            $caseService = app(CaseService::class);

            // Проверяем доступность множителя
            $maxOpens = $caseService->getMaxOpens($case, $client);
            if ($count > $maxOpens) {
                if ($maxOpens <= 0) {
                    throw new Exception('Недостаточно средств на балансе');
                }
                throw new Exception("Можно открыть максимум {$maxOpens}");
            }

            // Открываем кейс(ы)
            $result = $caseService->openCase($case, $client, $count);

            // Формируем призы для ответа
            $prizes = collect($result['items'])->map(function ($item) {
                $virtual = $item['case_item']->virtualItem;
                return [
                    'id' => $item['case_item']->id,
                    'inventory_id' => $item['inventory_item']->id,
                    'name' => $virtual->name,
                    'price' => (float) $item['case_item']->price,
                    'image_url' => $virtual->image_url,
                    'rarity' => $virtual->rarity,
                    'rarity_color' => $virtual->rarity_color,
                ];
            });

            // Обновляем данные клиента
            $client->refresh();

            return response()->json([
                'success' => true,
                'prizes' => $prizes,
                'balance' => [
                    'main' => (float) $client->balance,
                    'bonus' => (float) $client->bonus_balance,
                ],
                'payment' => $result['payment'],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Получить последние дропы для лайв-ленты
     */
    public function liveFeed(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 30), 100);

        $drops = CaseOpen::with(['client', 'case', 'inventoryItem.virtualItem'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($caseOpen) {
                $client = $caseOpen->client;
                $case = $caseOpen->case;
                $inventoryItem = $caseOpen->inventoryItem;
                $virtualItem = $inventoryItem?->virtualItem;

                if (!$virtualItem) {
                    return null;
                }

                return [
                    'id' => $caseOpen->id,
                    'user' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'avatar' => $client->steam_avatar,
                    ],
                    'case' => [
                        'id' => $case->id,
                        'slug' => $case->slug,
                        'name' => $case->name,
                        'image' => $case->image_url,
                    ],
                    'item' => [
                        'id' => $inventoryItem->id,
                        'name' => $virtualItem->name,
                        'price' => (float) $inventoryItem->price,
                        'image_url' => $virtualItem->image_url,
                        'rarity' => $virtualItem->rarity,
                        'quality' => $virtualItem->quality,
                    ],
                    'timestamp' => $caseOpen->created_at->toISOString(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'drops' => $drops,
        ]);
    }
}
