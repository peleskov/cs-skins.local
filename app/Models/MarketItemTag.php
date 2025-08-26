<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketItemTag extends Model
{
    public $timestamps = false;
    
    protected $primaryKey = ['market_hash_name', 'tag_id'];
    public $incrementing = false;
    
    protected $fillable = [
        'market_hash_name',
        'tag_id',
    ];

    /**
     * Связь с тегом
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * Получить все теги для market_hash_name
     */
    public static function getTagsForMarketItem(string $marketHashName)
    {
        return static::where('market_hash_name', $marketHashName)
            ->with('tag')
            ->get()
            ->pluck('tag');
    }

    /**
     * Синхронизировать теги для market_hash_name
     */
    public static function syncTagsForMarketItem(string $marketHashName, array $tagIds): void
    {
        // Удаляем старые связи
        static::where('market_hash_name', $marketHashName)->delete();
        
        // Добавляем новые связи
        $data = array_map(fn($tagId) => [
            'market_hash_name' => $marketHashName,
            'tag_id' => $tagId
        ], $tagIds);
        
        if (!empty($data)) {
            static::insert($data);
        }
    }
}