<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Services\SkinScreenshotService;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    /**
     * Главная страница маркетплейса
     */
    public function index(): View
    {
        // Инициализируем переменные
        $seller = null;
        $sellerStats = null;

        $query = Listing::with(['seller'])
            ->active()
            ->where('price', '>', 0);

        // Фильтр по продавцу
        $sellerId = request()->get('seller_id');

        if ($sellerId) {
            $query->where('seller_id', $sellerId);

            // Получаем информацию о продавце
            $seller = Client::find($sellerId);

            if ($seller) {
                // Получаем статистику продавца
                $sellerStats = [
                    'total_listings' => Listing::where('seller_id', $sellerId)
                        ->active()
                        ->count(),
                    'total_sales' => Order::where('seller_id', $sellerId)
                        ->where('status', Order::STATUS_COMPLETED)
                        ->count(),
                    'total_purchases' => Order::where('buyer_id', $sellerId)
                        ->where('status', Order::STATUS_COMPLETED)
                        ->count(),
                ];
            }
        }

        $featuredListings = $query
            ->orderBy('listed_at', 'desc')
            ->limit(24)
            ->get();
            
        // Добавляем статус корзины для каждого товара (читаем из сессии)
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        
        // Добавляем статус избранного для каждого товара (читаем из БД)
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', $featuredListings->pluck('id'))
                ->pluck('listing_id');
        }
        
        $currentUserId = auth('client')->id();
        
        $featuredListings->each(function ($listing) use ($cartItemIds, $favoriteItemIds, $currentUserId) {
            // Проверяем, является ли товар собственным
            $listing->is_own_item = $currentUserId && $listing->seller_id === $currentUserId;
            
            // Добавляем в корзину только если товар не собственный
            $listing->is_in_cart = !$listing->is_own_item && $cartItemIds->contains($listing->id);
            $listing->is_favorite = $favoriteItemIds->contains($listing->id);
        });
            
        $totalListings = Listing::active()
            ->where('price', '>', 0)
            ->count();
            
        $hasMorePages = $totalListings > 24;

        // Обновляем счетчик если есть фильтр по продавцу
        if ($sellerId) {
            $totalListings = Listing::where('seller_id', $sellerId)
                ->active()
                ->where('price', '>', 0)
                ->count();
        }

        return view('marketplace.index', compact('featuredListings', 'totalListings', 'hasMorePages', 'seller', 'sellerStats'));
    }

    /**
     * API endpoint для получения активных предложений
     */
    public function getListings(Request $request): JsonResponse
    {
        $query = Listing::with(['seller'])
            ->active()
            ->where('price', '>', 0);

        // Фильтр по продавцу
        if ($sellerId = $request->get('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        // Поиск по названию
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('inventory_item_name', 'LIKE', "%{$search}%")
                  ->orWhere('market_hash_name', 'LIKE', "%{$search}%");
            });
        }

        // Фильтр по типу оружия через новое поле type
        if ($types = $request->get('types')) {
            if (is_string($types)) {
                $types = explode(',', $types);
            }
            
            $query->whereIn('type', $types);
        }

        // Фильтр по редкости через новую систему тегов
        if ($rarities = $request->get('rarities')) {
            if (is_string($rarities)) {
                $rarities = explode(',', $rarities);
            }
            
            // Маппинг фронтенд значений в нормализованные значения тегов
            $rarityMapping = [
                'common' => 'consumer',
                'uncommon' => 'industrial',
                'rare' => 'milspec',
                'mythical' => 'restricted',
                'legendary' => 'classified',
                'ancient' => 'covert',
                'contraband' => 'contraband'
            ];
            
            $normalizedRarities = array_map(function($rarity) use ($rarityMapping) {
                return $rarityMapping[$rarity] ?? $rarity;
            }, $rarities);
            
            $rarityTagIds = Tag::where('category_code', 'rarity')
                ->whereIn('normalized_value', $normalizedRarities)
                ->pluck('id');
            
            if ($rarityTagIds->isNotEmpty()) {
                $query->whereExists(function ($subQuery) use ($rarityTagIds) {
                    $subQuery->select(DB::raw(1))
                        ->from('market_item_tags')
                        ->whereColumn('market_item_tags.market_hash_name', 'listings.market_hash_name')
                        ->whereIn('market_item_tags.tag_id', $rarityTagIds);
                });
            }
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

        // Фильтр по качеству (wear conditions)
        if ($wearConditions = $request->get('wear_conditions')) {
            if (!is_array($wearConditions)) {
                $wearConditions = [$wearConditions];
            }

            $query->where(function ($q) use ($wearConditions) {
                foreach ($wearConditions as $condition) {
                    switch($condition) {
                        case 'fn':
                            $q->orWhere(function($subQ) {
                                $subQ->whereRaw('COALESCE(float_value, wear_value) <= ?', [0.07]);
                            });
                            break;
                        case 'mw':
                            $q->orWhere(function($subQ) {
                                $subQ->whereRaw('COALESCE(float_value, wear_value) > ? AND COALESCE(float_value, wear_value) <= ?', [0.07, 0.15]);
                            });
                            break;
                        case 'ft':
                            $q->orWhere(function($subQ) {
                                $subQ->whereRaw('COALESCE(float_value, wear_value) > ? AND COALESCE(float_value, wear_value) <= ?', [0.15, 0.38]);
                            });
                            break;
                        case 'ww':
                            $q->orWhere(function($subQ) {
                                $subQ->whereRaw('COALESCE(float_value, wear_value) > ? AND COALESCE(float_value, wear_value) <= ?', [0.38, 0.45]);
                            });
                            break;
                        case 'bs':
                            $q->orWhere(function($subQ) {
                                $subQ->whereRaw('COALESCE(float_value, wear_value) > ?', [0.45]);
                            });
                            break;
                    }
                }
            });
        }

        // Фильтр по диапазону Float
        if ($minFloat = $request->get('min_float')) {
            $query->whereRaw('COALESCE(float_value, wear_value) >= ?', [(float)$minFloat]);
        }
        if ($maxFloat = $request->get('max_float')) {
            $query->whereRaw('COALESCE(float_value, wear_value) <= ?', [(float)$maxFloat]);
        }

        // Фильтр по фазам (для ножей и перчаток с фазами)
        if ($phases = $request->get('phases')) {
            if (!is_array($phases)) {
                $phases = [$phases];
            }

            // Mapping frontend значений фаз на paint_index
            $phasePaintIndexes = [];

            foreach ($phases as $phase) {
                switch ($phase) {
                    case 'phase1':
                        $phasePaintIndexes[] = 415; // Doppler Phase 1
                        break;
                    case 'phase2':
                        $phasePaintIndexes[] = 416; // Doppler Phase 2
                        break;
                    case 'phase3':
                        $phasePaintIndexes[] = 417; // Doppler Phase 3
                        break;
                    case 'phase4':
                        $phasePaintIndexes[] = 418; // Doppler Phase 4
                        break;
                    case 'ruby':
                        $phasePaintIndexes[] = 415; // Ruby использует тот же paint_index что и Phase 1
                        break;
                    case 'sapphire':
                        $phasePaintIndexes[] = 416; // Sapphire использует тот же paint_index что и Phase 2
                        break;
                    case 'blackpearl':
                        $phasePaintIndexes[] = 419; // Black Pearl
                        break;
                    case 'emerald':
                        $phasePaintIndexes[] = 618; // Gamma Doppler Emerald
                        break;
                }
            }

            if (!empty($phasePaintIndexes)) {
                $query->whereIn('paint_index', $phasePaintIndexes);
            }
        }

        // Фильтр по тегам через новую систему market_item_tags
        if ($tags = $request->get('tags')) {
            if (is_string($tags)) {
                $tags = explode(',', $tags);
            }
            
            // Группируем теги по типам для эффективной фильтрации
            $tagsByType = [];
            foreach ($tags as $tag) {
                if (strpos($tag, ':') !== false) {
                    [$type, $value] = explode(':', $tag, 2);
                    $tagsByType[$type][] = $value;
                }
            }
            
            foreach ($tagsByType as $type => $values) {
                if ($type === 'type') {
                    // Фильтрация категорий через прямое поле type в listings
                    $query->whereIn('type', $values);
                } else {
                    // Фильтрация через market_item_tags для остальных тегов
                    $tagIds = Tag::where('category_code', $type)
                        ->whereIn('normalized_value', $values)
                        ->pluck('id');
                    
                    if ($tagIds->isNotEmpty()) {
                        $query->whereExists(function ($subQuery) use ($tagIds) {
                            $subQuery->select(DB::raw(1))
                                ->from('market_item_tags')
                                ->whereColumn('market_item_tags.market_hash_name', 'listings.market_hash_name')
                                ->whereIn('market_item_tags.tag_id', $tagIds);
                        });
                    }
                }
            }
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'listed_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $validSorts = ['price', 'listed_at', 'wear_value'];
        if (in_array($sortBy, $validSorts)) {
            if ($sortBy === 'price') {
                // Явно преобразуем price в число для правильной сортировки
                $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $sortOrder);
            } elseif ($sortBy === 'wear_value') {
                // Сортировка по износу с учетом обоих полей (float_value имеет приоритет)
                // COALESCE выберет первое не NULL значение
                $query->orderByRaw('COALESCE(float_value, wear_value) ' . $sortOrder);
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

        // Добавляем переведённые значения редкости, wear_name и статусы корзины/избранного
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        
        // Добавляем статус избранного для каждого товара (читаем из БД)
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', collect($listings->items())->pluck('id'))
                ->pluck('listing_id');
        }
        
        $currentUserId = auth('client')->id();
        
        $items = collect($listings->items())->map(function ($listing) use ($cartItemIds, $favoriteItemIds, $currentUserId) {
            // Используем новую систему тегов для редкости
            // Редкость получается через structured_tags
            // Убеждаемся что wear_name вычислен
            $wearName = $listing->getWearNameAttribute();
            $listing->wear_name = $wearName ?: 'unknown';
            
            // Добавляем статус корзины (читаем из сессии)
            // Но только если товар не собственный
            $listing->is_own_item = $currentUserId && $listing->seller_id === $currentUserId;
            $listing->is_in_cart = !$listing->is_own_item && $cartItemIds->contains($listing->id);
            
            // Добавляем статус избранного (читаем из БД)
            $listing->is_favorite = $favoriteItemIds->contains($listing->id);
            
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
        $listing->load(['seller']);
        
        // Другие предложения этого же предмета
        $otherListings = Listing::with(['seller'])
            ->where('market_hash_name', $listing->market_hash_name)
            ->where('id', '!=', $listing->id)
            ->active()
            ->orderBy('price')
            ->limit(5)
            ->get();

        // Добавляем статус корзины для основного товара и похожих
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        $listing->is_in_cart = $cartItemIds->contains($listing->id);
        
        // Добавляем статус избранного для основного товара и похожих
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $allListingIds = collect([$listing->id])->merge($otherListings->pluck('id'));
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', $allListingIds)
                ->pluck('listing_id');
        }
        $listing->is_favorite = $favoriteItemIds->contains($listing->id);
        
        $otherListings->each(function ($otherListing) use ($cartItemIds, $favoriteItemIds) {
            $otherListing->is_in_cart = $cartItemIds->contains($otherListing->id);
            $otherListing->is_favorite = $favoriteItemIds->contains($otherListing->id);
        });

        return view('marketplace.show', compact('listing', 'otherListings'));
    }

    /**
     * Получение категорий с количеством активных предложений
     */
    public function getCategories(Request $request): JsonResponse
    {
        // Используем type из листингов (получается из тегов при создании)
        $query = Listing::select('type', DB::raw('COUNT(*) as items_count'))
            ->where('status', 'active')
            ->where('price', '>', 0)
            ->whereNotNull('type');

        // Применяем те же фильтры что и в getListings, кроме фильтра по типу
        
        // Поиск по названию
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('inventory_item_name', 'LIKE', "%{$search}%")
                  ->orWhere('market_hash_name', 'LIKE', "%{$search}%");
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

        // Фильтр по тегам новой системы
        if ($tags = $request->get('tags')) {
            if (is_string($tags)) {
                $tags = explode(',', $tags);
            }
            
            $tagsByType = [];
            foreach ($tags as $tag) {
                if (strpos($tag, ':') !== false) {
                    [$type, $value] = explode(':', $tag, 2);
                    $tagsByType[$type][] = $value;
                }
            }
            
            foreach ($tagsByType as $type => $values) {
                if ($type === 'type') {
                    // Фильтрация категорий через прямое поле type в listings
                    $query->whereIn('type', $values);
                } else {
                    // Фильтрация остальных тегов через market_item_tags
                    $tagIds = Tag::where('category_code', $type)
                        ->whereIn('normalized_value', $values)
                        ->pluck('id');
                    
                    if ($tagIds->isNotEmpty()) {
                        $query->whereExists(function($q) use ($tagIds) {
                            $q->select(DB::raw(1))
                                ->from('market_item_tags')
                                ->whereColumn('market_item_tags.market_hash_name', 'listings.market_hash_name')
                                ->whereIn('market_item_tags.tag_id', $tagIds);
                        });
                    }
                }
            }
        }

        $categories = $query->groupBy('type')
            ->having('items_count', '>', 0)
            ->get()
            ->map(function ($item) {
                // Теперь type уже содержит нормализованное значение из тегов
                $typeKey = $item->type;
                
                return [
                    'type' => $typeKey,
                    'name' => __("items.types.{$typeKey}"),
                    'count' => $item->items_count,
                ];
            })
            ->groupBy('type')
            ->map(function ($group) {
                return [
                    'type' => $group->first()['type'],
                    'name' => $group->first()['name'],
                    'count' => $group->sum('count'),
                ];
            })
            ->values();

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
            $activeListings->where(function ($q) use ($search) {
                $q->where('inventory_item_name', 'LIKE', "%{$search}%")
                  ->orWhere('market_hash_name', 'LIKE', "%{$search}%");
            });
        }

        if ($minPrice = $request->get('min_price')) {
            $activeListings->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->get('max_price')) {
            $activeListings->where('price', '<=', $maxPrice);
        }

        if ($types = $request->get('types')) {
            if (is_string($types)) {
                $types = explode(',', $types);
            }
            
            // Преобразуем английские ключи обратно в русские названия
            $typeMapping = [
                'rifle' => 'Винтовка',
                'pistol' => 'Пистолет',
                'smg' => 'Пистолет-пулемёт',
                'sniper_rifle' => 'Снайперская винтовка',
                'shotgun' => 'Дробовик',
                'machinegun' => 'Пулемёт',
                'knife' => 'Нож',
                'gloves' => 'Перчатки',
                'sticker' => 'Наклейка'
            ];
            
            $russianTypes = array_map(function($type) use ($typeMapping) {
                return $typeMapping[$type] ?? $type;
            }, $types);
            
            $activeListings->where(function ($q) use ($russianTypes) {
                // Ищем в поле type
                foreach ($russianTypes as $type) {
                    $q->orWhere('type', $type);
                }
            });
        }

        // Фильтр StatTrak
        if ($request->has('stattrak')) {
            $activeListings->where('is_stattrak', $request->boolean('stattrak'));
        }

        // Фильтр Souvenir
        if ($request->has('souvenir')) {
            $activeListings->where('is_souvenir', $request->boolean('souvenir'));
        }

        // Фильтр по диапазону износа
        if ($wearRanges = $request->get('wear_range')) {
            if (!is_array($wearRanges)) {
                $wearRanges = [$wearRanges];
            }
            
            $activeListings->where(function ($q) use ($wearRanges) {
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

        // Фильтр по тегам новой системы
        if ($tags = $request->get('tags')) {
            if (is_string($tags)) {
                $tags = explode(',', $tags);
            }
            
            $tagsByType = [];
            foreach ($tags as $tag) {
                if (strpos($tag, ':') !== false) {
                    [$type, $value] = explode(':', $tag, 2);
                    $tagsByType[$type][] = $value;
                }
            }
            
            foreach ($tagsByType as $type => $values) {
                // Все фильтры теперь используют новую систему через market_item_tags
                $tagIds = Tag::where('category_code', $type)
                    ->whereIn('normalized_value', $values)
                    ->pluck('id');
                
                if ($tagIds->isNotEmpty()) {
                    $activeListings->whereExists(function($q) use ($tagIds) {
                        $q->select(DB::raw(1))
                            ->from('market_item_tags')
                            ->whereColumn('market_item_tags.market_hash_name', 'listings.market_hash_name')
                            ->whereIn('market_item_tags.tag_id', $tagIds);
                    });
                }
            }
        }

        $tags = [];
        
        // Получаем основные теги через прямые связи (эффективно)
        $primaryTags = $this->getPrimaryTags($activeListings);
        $tags = array_merge($tags, $primaryTags);
        
        // Получаем дополнительные теги через item_tags (для коллекций, турниров и т.д.)
        $additionalTags = $this->getAdditionalTags($activeListings);
        $tags = array_merge($tags, $additionalTags);
        
        // Добавляем статические теги (StatTrak, Souvenir, износ)
        $staticTags = $this->getStaticTags($activeListings);
        $tags = array_merge($tags, $staticTags);

        // Удаляем дубликаты по комбинации type + value
        $uniqueTags = [];
        $seenKeys = [];
        
        foreach ($tags as $tag) {
            $key = $tag['type'] . ':' . $tag['value'];
            if (!in_array($key, $seenKeys)) {
                $uniqueTags[] = $tag;
                $seenKeys[] = $key;
            }
        }

        return response()->json($uniqueTags);
    }
    
    /**
     * Получение основных тегов через новую систему
     */
    private function getPrimaryTags($activeListings): array
    {
        $tags = [];
        
        // Получаем market_hash_name из активных листингов
        $marketHashNames = $activeListings->pluck('market_hash_name')->unique();
        
        if ($marketHashNames->isEmpty()) {
            return $tags;
        }
        
        // Получаем основные категории тегов (убираем type, т.к. он отображается в Категориях)
        $primaryCategories = ['quality', 'rarity', 'exterior'];
        
        foreach ($primaryCategories as $category) {
            // Подсчитываем теги для данной категории через новую систему
            $tagCounts = DB::table('tags')
                ->join('market_item_tags', 'tags.id', '=', 'market_item_tags.tag_id')
                ->whereIn('market_item_tags.market_hash_name', $marketHashNames)
                ->where('tags.category_code', $category)
                ->select('tags.id', 'tags.normalized_value', DB::raw('COUNT(DISTINCT market_item_tags.market_hash_name) as count'))
                ->groupBy('tags.id', 'tags.normalized_value')
                ->get();
                
            foreach ($tagCounts as $tagCount) {
                $tags[] = [
                    'type' => $category,
                    'name' => $tagCount->normalized_value,
                    'count' => $tagCount->count,
                    'value' => $tagCount->normalized_value,
                    'color' => null
                ];
            }
        }
        
        return $tags;
    }
    
    /**
     * Получение дополнительных тегов через новую систему
     */
    private function getAdditionalTags($activeListings): array
    {
        $tags = [];
        
        // Получаем market_hash_name из активных листингов
        $marketHashNames = $activeListings->pluck('market_hash_name')->unique();
        
        if ($marketHashNames->isEmpty()) {
            return $tags;
        }
        
        // Получаем дополнительные категории (не основные, исключаем type - он в Категориях)
        $primaryCategories = ['quality', 'rarity', 'exterior'];
        
        $additionalTags = DB::table('tags')
            ->join('market_item_tags', 'tags.id', '=', 'market_item_tags.tag_id')
            ->whereIn('market_item_tags.market_hash_name', $marketHashNames)
            ->whereNotIn('tags.category_code', $primaryCategories)
            ->select(
                'tags.category_code',
                'tags.normalized_value',
                DB::raw('COUNT(DISTINCT market_item_tags.market_hash_name) as count')
            )
            ->groupBy('tags.category_code', 'tags.normalized_value')
            ->get();
            
        foreach ($additionalTags as $tag) {
            $tags[] = [
                'type' => $tag->category_code,
                'name' => $tag->normalized_value,
                'count' => $tag->count,
                'value' => $tag->normalized_value,
                'color' => null
            ];
        }
        
        return $tags;
    }
    
    /**
     * Получение статических тегов (StatTrak, Souvenir, износ)
     */
    private function getStaticTags($activeListings): array
    {
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


        return $tags;
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

        $suggestions = Listing::select('inventory_item_name', 'market_hash_name', 'inventory_icon_url')
            ->active()
            ->where('price', '>', 0)
            ->where(function ($q) use ($query) {
                $q->where('inventory_item_name', 'LIKE', "%{$query}%")
                  ->orWhere('market_hash_name', 'LIKE', "%{$query}%");
            })
            ->distinct()
            ->limit(10)
            ->get()
            ->map(function ($listing) {
                $iconUrl = $listing->inventory_icon_url;
                if ($iconUrl && !str_starts_with($iconUrl, 'http')) {
                    $iconUrl = 'https://community.steamstatic.com/economy/image/' . $iconUrl;
                }
                
                return [
                    'name_ru' => $listing->inventory_item_name,
                    'name_en' => $listing->market_hash_name,
                    'image_url' => $iconUrl,
                ];
            });

        return response()->json($suggestions);
    }

    /**
     * Получение похожих предложений для предмета
     */
    public function getSimilarListings(Listing $listing): JsonResponse
    {
        $similarListings = Listing::with(['seller'])
            ->where('market_hash_name', $listing->market_hash_name)
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
                        'name_ru' => $listing->inventory_item_name,
                        'name_en' => $listing->market_hash_name,
                        'image_url' => $listing->inventory_icon_url,
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
        $listing->load(['seller', 'inventoryItem.steamMarketItem.priceHistory']);
        
        // Другие предложения этого же предмета
        $otherListings = Listing::with(['seller'])
            ->where('market_hash_name', $listing->market_hash_name)
            ->where('id', '!=', $listing->id)
            ->active()
            ->orderBy('price')
            ->limit(5)
            ->get();

        // Добавляем статус корзины
        $cartItemIds = collect(session()->get('shopping_cart', []))->keys();
        $listing->is_in_cart = $cartItemIds->contains($listing->id);
        
        // Добавляем статус избранного
        $favoriteItemIds = collect();
        if (auth('client')->check()) {
            $allListingIds = collect([$listing->id])->merge($otherListings->pluck('id'));
            $favoriteItemIds = Favorite::where('client_id', auth('client')->id())
                ->whereIn('listing_id', $allListingIds)
                ->pluck('listing_id');
        }
        $listing->is_favorite = $favoriteItemIds->contains($listing->id);
        
        $otherListings->each(function ($otherListing) use ($cartItemIds, $favoriteItemIds) {
            $otherListing->is_in_cart = $cartItemIds->contains($otherListing->id);
            $otherListing->is_favorite = $favoriteItemIds->contains($otherListing->id);
        });

        // Получаем историю цен Steam Market за последние 30 дней
        $steamPriceHistory = [];
        $steamPriceStats = null;
        
        if ($listing->inventoryItem?->steamMarketItem?->priceHistory) {
            $priceHistory = $listing->inventoryItem->steamMarketItem->priceHistory()
                ->where('date', '>=', now()->subDays(30))
                ->orderBy('date')
                ->get(['date', 'price', 'volume']);
                
            $steamPriceHistory = $priceHistory->toArray();
            
            if ($priceHistory->count() > 0) {
                $steamPriceStats = [
                    'avg_price' => round($priceHistory->avg('price'), 2),
                    'min_price' => round($priceHistory->min('price'), 2),
                    'max_price' => round($priceHistory->max('price'), 2),
                    'total_volume' => $priceHistory->sum('volume'),
                ];
            }
        }

        return response()->json([
            'listing' => [
                'id' => $listing->id,
                'price' => (float) $listing->price,
                'wear_value' => (float) $listing->wear_value,
                'wear_name' => $listing->wear_name,
                'float_value' => $listing->float_value !== null ? (float) $listing->float_value : null,
                'float_min' => $listing->float_min !== null ? (float) $listing->float_min : null,
                'float_max' => $listing->float_max !== null ? (float) $listing->float_max : null,
                'paint_index' => $listing->paint_index,
                'def_index' => $listing->def_index,
                'csfloat_id' => $listing->csfloat_id,
                'is_stattrak' => $listing->is_stattrak,
                'is_souvenir' => $listing->is_souvenir,
                'pattern_index' => $listing->pattern_index,
                'inventory_item_name' => $listing->inventory_item_name,
                'market_hash_name' => $listing->market_hash_name,
                'inventory_icon_url' => $listing->inventory_icon_url,
                'type' => $listing->type,
                'inventory_descriptions' => $listing->inventory_descriptions,
                'inspect_url' => $listing->inspect_url,
                'steam_asset_id' => $listing->steam_asset_id,
                'screenshots' => $listing->screenshots,
                'screenshot_urls' => $listing->steam_asset_id ? SkinScreenshotService::generateScreenshotUrls($listing->steam_asset_id) : null,
                'tags' => $listing->structured_tags,
                'is_in_cart' => $listing->is_in_cart,
                'is_favorite' => $listing->is_favorite,
                'seller' => [
                    'id' => $listing->seller->id,
                    'name' => $listing->seller->name,
                ],
                'steam_price_history' => $steamPriceHistory,
                'steam_price_stats' => $steamPriceStats,
            ],
            'otherListings' => $otherListings->map(function ($other) {
                return [
                    'id' => $other->id,
                    'price' => (float) $other->price,
                    'wear_value' => (float) $other->wear_value,
                    'wear_name' => $other->wear_name,
                    'float_value' => $other->float_value !== null ? (float) $other->float_value : null,
                    'float_min' => $other->float_min !== null ? (float) $other->float_min : null,
                    'float_max' => $other->float_max !== null ? (float) $other->float_max : null,
                    'is_in_cart' => $other->is_in_cart,
                    'is_favorite' => $other->is_favorite,
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
