<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    /**
     * Главная страница маркетплейса
     */
    public function index(): View
    {
        $featuredListings = Listing::with(['item', 'seller'])
            ->active()
            ->where('price', '>', 0)
            ->orderBy('listed_at', 'desc')
            ->limit(24)
            ->get();
            
        $totalListings = Listing::active()
            ->where('price', '>', 0)
            ->count();
            
        $hasMorePages = $totalListings > 24;
            
        return view('marketplace.index', compact('featuredListings', 'totalListings', 'hasMorePages'));
    }

    /**
     * API endpoint для получения активных предложений
     */
    public function getListings(Request $request): JsonResponse
    {
        $query = Listing::with(['item', 'seller'])
            ->active()
            ->where('price', '>', 0);

        // Поиск по названию
        if ($search = $request->get('search')) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('name_ru', 'LIKE', "%{$search}%")
                  ->orWhere('name_en', 'LIKE', "%{$search}%");
            });
        }

        // Фильтр по типу оружия
        if ($types = $request->get('types')) {
            if (is_string($types)) {
                $types = explode(',', $types);
            }
            $query->whereHas('item', function ($q) use ($types) {
                $q->whereIn('type', $types);
            });
        }

        // Фильтр по редкости
        if ($rarities = $request->get('rarities')) {
            if (is_string($rarities)) {
                $rarities = explode(',', $rarities);
            }
            $query->whereHas('item', function ($q) use ($rarities) {
                $q->whereIn('rarity', $rarities);
            });
        }

        // Фильтр по цене
        if ($minPrice = $request->get('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        // Фильтр StatTrak
        if ($request->has('stattrak')) {
            $query->where('is_stattrak', $request->boolean('stattrak'));
        }

        // Фильтр Souvenir
        if ($request->has('souvenir')) {
            $query->where('is_souvenir', $request->boolean('souvenir'));
        }

        // Фильтр по диапазону износа (поддержка множественного выбора)
        if ($wearRanges = $request->get('wear_range')) {
            // Поддержка как одиночного значения, так и массива
            if (!is_array($wearRanges)) {
                $wearRanges = [$wearRanges];
            }
            
            $query->where(function ($q) use ($wearRanges) {
                foreach ($wearRanges as $wearRange) {
                    $wearValue = (float) $wearRange;
                    if ($wearValue <= 0.07) {
                        $q->orWhere('wear_value', '<=', 0.07);
                    } elseif ($wearValue <= 0.15) {
                        $q->orWhereBetween('wear_value', [0.07, 0.15]);
                    } elseif ($wearValue <= 0.38) {
                        $q->orWhereBetween('wear_value', [0.15, 0.38]);
                    } elseif ($wearValue <= 0.45) {
                        $q->orWhereBetween('wear_value', [0.38, 0.45]);
                    } else {
                        $q->orWhere('wear_value', '>', 0.45);
                    }
                }
            });
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'listed_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $validSorts = ['price', 'listed_at', 'wear_value'];
        if (in_array($sortBy, $validSorts)) {
            if ($sortBy === 'price') {
                // Явно преобразуем price в число для правильной сортировки
                $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // Сортировка по умолчанию
            $query->orderBy('listed_at', 'desc');
        }

        // Пагинация
        $perPage = min($request->get('per_page', 24), 50); // Максимум 50 за раз
        $listings = $query->paginate($perPage);

        // Добавляем переведённые значения редкости и wear_name
        $items = collect($listings->items())->map(function ($listing) {
            $listing->item->rarity_translated = __('items.rarities.' . $listing->item->rarity);
            $listing->wear_name = $listing->wear_name; // Это вызовет геттер из модели
            return $listing;
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'has_more_pages' => $listings->hasMorePages(),
            ]
        ]);
    }

    /**
     * Детальная страница предмета
     */
    public function show(Listing $listing): View
    {
        $listing->load(['item', 'seller']);
        
        // Другие предложения этого же предмета
        $otherListings = Listing::with(['seller'])
            ->where('item_id', $listing->item_id)
            ->where('id', '!=', $listing->id)
            ->active()
            ->orderBy('price')
            ->limit(5)
            ->get();

        return view('marketplace.show', compact('listing', 'otherListings'));
    }

    /**
     * Получение категорий с количеством активных предложений
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = Item::select('items.type', \DB::raw('COUNT(DISTINCT listings.id) as items_count'))
            ->join('listings', 'items.id', '=', 'listings.item_id')
            ->where('listings.status', 'active')
            ->where('listings.price', '>', 0);

        // Применяем те же фильтры что и в getListings, кроме фильтра по типу
        
        // Поиск по названию
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('items.name_ru', 'LIKE', "%{$search}%")
                  ->orWhere('items.name_en', 'LIKE', "%{$search}%");
            });
        }

        // Фильтр по цене
        if ($minPrice = $request->get('min_price')) {
            $query->where('listings.price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $query->where('listings.price', '<=', $maxPrice);
        }

        // Фильтр StatTrak
        if ($request->has('stattrak')) {
            $query->where('listings.is_stattrak', $request->boolean('stattrak'));
        }

        // Фильтр Souvenir
        if ($request->has('souvenir')) {
            $query->where('listings.is_souvenir', $request->boolean('souvenir'));
        }

        // Фильтр по диапазону износа (поддержка множественного выбора)
        if ($wearRanges = $request->get('wear_range')) {
            // Поддержка как одиночного значения, так и массива
            if (!is_array($wearRanges)) {
                $wearRanges = [$wearRanges];
            }
            
            $query->where(function ($q) use ($wearRanges) {
                foreach ($wearRanges as $wearRange) {
                    $wearValue = (float) $wearRange;
                    if ($wearValue <= 0.07) {
                        $q->orWhere('listings.wear_value', '<=', 0.07);
                    } elseif ($wearValue <= 0.15) {
                        $q->orWhereBetween('listings.wear_value', [0.07, 0.15]);
                    } elseif ($wearValue <= 0.38) {
                        $q->orWhereBetween('listings.wear_value', [0.15, 0.38]);
                    } elseif ($wearValue <= 0.45) {
                        $q->orWhereBetween('listings.wear_value', [0.38, 0.45]);
                    } else {
                        $q->orWhere('listings.wear_value', '>', 0.45);
                    }
                }
            });
        }

        $categories = $query->groupBy('items.type')
            ->having('items_count', '>', 0)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'name' => __("items.types.{$item->type}"),
                    'count' => $item->items_count,
                ];
            });

        return response()->json($categories);
    }

    /**
     * Получение статистики для фильтров
     */
    public function getFilterStats(): JsonResponse
    {
        $activeListings = Listing::active()->where('price', '>', 0);
        
        $stats = [
            'price_range' => [
                'min' => $activeListings->min('price'),
                'max' => $activeListings->max('price'),
                'avg' => round($activeListings->avg('price'), 2),
            ],
            'total_listings' => $activeListings->count(),
            'stattrak_count' => $activeListings->where('is_stattrak', true)->count(),
            'souvenir_count' => $activeListings->where('is_souvenir', true)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Получение доступных тегов с количеством предложений
     */
    public function getTags(Request $request): JsonResponse
    {
        $activeListings = Listing::active()->where('price', '>', 0);
        
        // Применяем фильтры (кроме самих тегов)
        if ($search = $request->get('search')) {
            $activeListings->whereHas('item', function ($q) use ($search) {
                $q->where('name_ru', 'LIKE', "%{$search}%")
                  ->orWhere('name_en', 'LIKE', "%{$search}%");
            });
        }

        if ($minPrice = $request->get('min_price')) {
            $activeListings->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $activeListings->where('price', '<=', $maxPrice);
        }

        if ($types = $request->get('types')) {
            $activeListings->whereHas('item', function ($q) use ($types) {
                $q->where('type', $types);
            });
        }

        $tags = [];
        
        // StatTrak
        $stattrakCount = (clone $activeListings)->where('is_stattrak', true)->count();
        if ($stattrakCount > 0) {
            $tags[] = [
                'type' => 'stattrak',
                'name' => 'StatTrak',
                'count' => $stattrakCount,
                'value' => true
            ];
        }

        // Souvenir
        $souvenirCount = (clone $activeListings)->where('is_souvenir', true)->count();
        if ($souvenirCount > 0) {
            $tags[] = [
                'type' => 'souvenir',
                'name' => 'Souvenir',
                'count' => $souvenirCount,
                'value' => true
            ];
        }

        // Состояния износа
        $wearStates = [
            ['min' => 0, 'max' => 0.07, 'name' => 'Прямо с завода', 'value' => '0.07'],
            ['min' => 0.07, 'max' => 0.15, 'name' => 'Немного поношенное', 'value' => '0.15'],
            ['min' => 0.15, 'max' => 0.38, 'name' => 'После полевых испытаний', 'value' => '0.38'],
            ['min' => 0.38, 'max' => 0.45, 'name' => 'Поношенное', 'value' => '0.45'],
            ['min' => 0.45, 'max' => 1.0, 'name' => 'Закалённое в боях', 'value' => '1.0'],
        ];

        foreach ($wearStates as $wear) {
            $wearCount = (clone $activeListings)->whereBetween('wear_value', [$wear['min'], $wear['max']])->count();
            if ($wearCount > 0) {
                $tags[] = [
                    'type' => 'wear',
                    'name' => $wear['name'],
                    'count' => $wearCount,
                    'value' => $wear['value']
                ];
            }
        }

        return response()->json($tags);
    }

    /**
     * Поиск предложений с автодополнением
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = Item::select('name_ru', 'name_en', 'image_url')
            ->whereHas('activeListings')
            ->where(function ($q) use ($query) {
                $q->where('name_ru', 'LIKE', "%{$query}%")
                  ->orWhere('name_en', 'LIKE', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name_ru' => $item->name_ru,
                    'name_en' => $item->name_en,
                    'image_url' => $item->image_url,
                ];
            });

        return response()->json($suggestions);
    }

    /**
     * Получение похожих предложений для предмета
     */
    public function getSimilarListings(Listing $listing): JsonResponse
    {
        $similarListings = Listing::with(['item', 'seller'])
            ->where('item_id', $listing->item_id)
            ->where('id', '!=', $listing->id)
            ->active()
            ->orderBy('price')
            ->limit(10)
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'price' => $listing->price,
                    'wear_value' => $listing->wear_value,
                    'wear_name' => $listing->wear_name,
                    'is_stattrak' => $listing->is_stattrak,
                    'is_souvenir' => $listing->is_souvenir,
                    'seller' => [
                        'id' => $listing->seller->id,
                        'name' => $listing->seller->name,
                    ],
                    'item' => [
                        'id' => $listing->item->id,
                        'name_ru' => $listing->item->name_ru,
                        'name_en' => $listing->item->name_en,
                        'image_url' => $listing->item->image_url,
                    ],
                ];
            });

        return response()->json($similarListings);
    }

    /**
     * API endpoint для получения детальной информации о листинге
     */
    public function getListingDetails(Listing $listing): JsonResponse
    {
        $listing->load(['item', 'seller']);
        
        // Другие предложения этого же предмета
        $otherListings = Listing::with(['seller'])
            ->where('item_id', $listing->item_id)
            ->where('id', '!=', $listing->id)
            ->active()
            ->orderBy('price')
            ->limit(5)
            ->get();

        return response()->json([
            'listing' => [
                'id' => $listing->id,
                'price' => (float) $listing->price,
                'wear_value' => (float) $listing->wear_value,
                'wear_name' => $listing->wear_name,
                'is_stattrak' => $listing->is_stattrak,
                'is_souvenir' => $listing->is_souvenir,
                'pattern_index' => $listing->pattern_index,
                'seller' => [
                    'id' => $listing->seller->id,
                    'name' => $listing->seller->name,
                ],
                'item' => [
                    'id' => $listing->item->id,
                    'name_ru' => $listing->item->name_ru,
                    'name_en' => $listing->item->name_en,
                    'description_ru' => $listing->item->description_ru,
                    'description_en' => $listing->item->description_en,
                    'type' => $listing->item->type,
                    'rarity' => $listing->item->rarity,
                    'image_url' => $listing->item->image_url,
                    'image_fn' => $listing->item->image_fn,
                    'image_mw' => $listing->item->image_mw,
                    'image_ft' => $listing->item->image_ft,
                    'image_ww' => $listing->item->image_ww,
                    'image_bs' => $listing->item->image_bs,
                    'min_steam_price' => (float) $listing->item->min_steam_price,
                    'steam_price_rub' => (float) $listing->item->steam_price_rub,
                    'buyout_price' => (float) $listing->item->buyout_price,
                    'steam_listings_count' => $listing->item->steam_listings_count,
                    'steam_market_hash_name' => $listing->item->steam_market_hash_name,
                    'is_valid' => $listing->item->is_valid,
                ],
            ],
            'otherListings' => $otherListings->map(function ($other) {
                return [
                    'id' => $other->id,
                    'price' => (float) $other->price,
                    'wear_value' => (float) $other->wear_value,
                    'wear_name' => $other->wear_name,
                    'seller' => [
                        'id' => $other->seller->id,
                        'name' => $other->seller->name,
                    ],
                ];
            }),
        ]);
    }

    /**
     * API endpoint для получения переводов
     */
    public function getTranslations(): JsonResponse
    {
        return response()->json([
            'types' => [
                'rifle' => __('items.types.rifle'),
                'sniper_rifle' => __('items.types.sniper_rifle'),
                'pistol' => __('items.types.pistol'),
                'smg' => __('items.types.smg'),
                'shotgun' => __('items.types.shotgun'),
                'machinegun' => __('items.types.machinegun'),
                'knife' => __('items.types.knife'),
                'gloves' => __('items.types.gloves'),
                'agent' => __('items.types.agent'),
                'sticker' => __('items.types.sticker'),
                'graffiti' => __('items.types.graffiti'),
                'patch' => __('items.types.patch'),
                'collectible' => __('items.types.collectible'),
                'key' => __('items.types.key'),
                'case' => __('items.types.case'),
                'music_kit' => __('items.types.music_kit'),
                'pin' => __('items.types.pin'),
                'tool' => __('items.types.tool'),
            ],
            'rarities' => [
                'consumer_grade' => __('items.rarities.consumer_grade'),
                'industrial_grade' => __('items.rarities.industrial_grade'),
                'mil_spec' => __('items.rarities.mil_spec'),
                'restricted' => __('items.rarities.restricted'),
                'classified' => __('items.rarities.classified'),
                'covert' => __('items.rarities.covert'),
                'knife' => __('items.rarities.knife'),
                'gloves' => __('items.rarities.gloves'),
                'contraband' => __('items.rarities.contraband'),
            ],
        ]);
    }
}
