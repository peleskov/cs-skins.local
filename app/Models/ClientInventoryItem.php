<?php

namespace App\Models;

use App\Services\BotRotationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tag;
use App\Models\SteamMarketItem;
use App\Models\SteamPriceHistory;
use App\Models\RarityCoefficient;
use App\Models\Currency;

class ClientInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'steam_asset_id',
        'steam_class_id',
        'steam_instance_id',
        'market_hash_name',
        'item_name',
        'icon_url',
        'tradable',
        'marketable',
        'amount',
        'float_value',
        'float_min',
        'float_max',
        'paint_index',
        'def_index',
        'csfloat_id',
        'pattern_index',
        'stickers',
        'inspect_url',
        'descriptions',
        'cached_at',
        'float_fetched_at',
        'item_nameid',
        'item_nameid_fetched_at',
    ];

    protected $casts = [
        'tradable' => 'boolean',
        'marketable' => 'boolean',
        'amount' => 'integer',
        'float_value' => 'float',
        'float_min' => 'float',
        'float_max' => 'float',
        'paint_index' => 'integer',
        'def_index' => 'integer',
        'csfloat_id' => 'integer',
        'pattern_index' => 'integer',
        'stickers' => 'array',
        'descriptions' => 'array',
        'cached_at' => 'datetime',
        'float_fetched_at' => 'datetime',
        'item_nameid_fetched_at' => 'datetime',
    ];

    protected $appends = ['structured_tags'];


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caseItems(): HasMany
    {
        return $this->hasMany(CaseItem::class, 'inventory_item_id');
    }


    public function steamMarketItem(): BelongsTo
    {
        return $this->belongsTo(SteamMarketItem::class, 'market_hash_name', 'market_hash_name');
    }

    public function scopeTradable($query)
    {
        return $query->where('tradable', true);
    }

    public function scopeMarketable($query)
    {
        return $query->where('marketable', true);
    }

    public function getFullIconUrlAttribute(): ?string
    {
        if (!$this->icon_url) {
            return null;
        }
        
        return 'https://community.steamstatic.com/economy/image/' . $this->icon_url;
    }

    public function hasWear(): bool
    {
        return $this->float_value !== null;
    }

    public function getWearConditionAttribute(): ?string
    {
        if (!$this->hasWear()) {
            return null;
        }

        $float = $this->float_value;

        if ($float >= 0.45) return 'Battle-Scarred';
        if ($float >= 0.38) return 'Well-Worn';
        if ($float >= 0.15) return 'Field-Tested';
        if ($float >= 0.07) return 'Minimal Wear';
        return 'Factory New';
    }

    public function hasStickers(): bool
    {
        return !empty($this->stickers);
    }

    public function getStickerCountAttribute(): int
    {
        return count($this->stickers ?? []);
    }

    public function isFromDatabase(): bool
    {
        return $this->exists;
    }

    /**
     * Получить теги через market_hash_name
     */
    public function tags()
    {
        return Tag::join('market_item_tags', 'tags.id', '=', 'market_item_tags.tag_id')
            ->where('market_item_tags.market_hash_name', $this->market_hash_name)
            ->orderBy('tags.category_code')
            ->orderBy('tags.sort_order')
            ->get();
    }

    public function getStructuredTagsAttribute()
    {
        $tags = $this->tags();
        
        if ($tags && $tags->isNotEmpty()) {
            return $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'category_code' => $tag->category_code,
                    'category_name' => $tag->category_name, // Использует геттер с переводами
                    'display_name' => $tag->localized_name, // Использует геттер с переводами
                    'normalized_value' => $tag->normalized_value,
                ];
            });
        }
        
        return collect();
    }

    /**
     * Получить текущую рыночную цену предмета
     * @return float|null Цена в RUB или null если нет данных
     */
    public function getCurrentPrice(): ?float
    {
        // Находим запись в steam_market_items
        $steamMarketItem = SteamMarketItem::where('market_hash_name', $this->market_hash_name)->first();
        if (!$steamMarketItem) {
            return null;
        }

        // Получаем последнюю цену из истории
        $latestPrice = SteamPriceHistory::where('steam_market_item_id', $steamMarketItem->id)
            ->orderBy('date', 'desc')
            ->first();

        if (!$latestPrice) {
            return null;
        }

        // Конвертируем из USD в RUB (основная валюта проекта)
        return Currency::convert($latestPrice->price, 'USD', 'RUB');
    }

    /**
     * Рассчитать рыночную цену предмета с учётом коэффициента редкости
     * @param bool $checkBot Проверять ли доступность бота (true для выкупа, false для рекомендуемой цены)
     * @return float|null Цена в USD или null если нет данных
     */
    public function calculateBuyoutPrice(bool $checkBot = true): ?float
    {
        // Проверяем что предмет можно продать
        if (!$this->tradable || !$this->marketable) {
            return null;
        }

        // Получаем цену: сначала из virtual_items, затем fallback на steam_price_history
        $priceUsd = null;

        // 1. Ищем в virtual_items (актуальный каталог с ценами Steam)
        $virtualItem = VirtualItem::where('market_hash_name', $this->market_hash_name)
            ->where('steam_price', '>', 0)
            ->first();

        if ($virtualItem) {
            $priceUsd = (float) $virtualItem->steam_price;
        }

        // 2. Fallback: steam_price_history
        if (!$priceUsd) {
            $steamMarketItem = SteamMarketItem::where('market_hash_name', $this->market_hash_name)->first();
            if ($steamMarketItem) {
                $latestPrice = SteamPriceHistory::where('steam_market_item_id', $steamMarketItem->id)
                    ->orderBy('date', 'desc')
                    ->first();
                if ($latestPrice && $latestPrice->price > 0 && $latestPrice->volume >= 200) {
                    $priceUsd = (float) $latestPrice->price;
                }
            }
        }

        if (!$priceUsd) {
            return null;
        }

        // Получаем теги
        $tags = $this->tags();

        // Ищем тег редкости
        $rarityTag = $tags->first(function ($tag) {
            return $tag->category_code === 'rarity';
        });

        // Если нет тега редкости - предмет не может быть выкуплен
        if (!$rarityTag) {
            return null;
        }

        // Получаем коэффициент из БД
        $coefficient = RarityCoefficient::getCoefficientByName($rarityTag->normalized_value);

        // Если коэффициент не найден - не выкупаем
        if (!$coefficient) {
            return null;
        }

        // Рассчитываем цену выкупа в USD
        $buyoutPrice = round($priceUsd * $coefficient, 2);

        // Проверяем доступность бота только для реального выкупа
        if ($checkBot) {
            $buyoutPriceRub = Currency::convert($buyoutPrice, 'USD', 'RUB');
            $botService = new BotRotationService();
            $availableBot = $botService->getNextAvailableBot($buyoutPriceRub);

            if (!$availableBot) {
                return null;
            }
        }

        return $buyoutPrice;
    }
}
