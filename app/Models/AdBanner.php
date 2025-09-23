<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdBanner extends Model
{
    protected $table = 'ad_banner';

    protected $fillable = [
        'image',
        'content',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
        'content' => 'array'
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($banner) {
            if ($banner->active) {
                static::where('id', '!=', $banner->id)->update(['active' => false]);
            }
        });
    }

    public function getContentByLanguage($lang = null)
    {
        if (!$lang) {
            $lang = app()->getLocale();
        }

        if (!$this->content || !is_array($this->content)) {
            return null;
        }

        // Ищем контент для текущего языка
        $content = collect($this->content)->firstWhere('lang', $lang);

        // Если не найден, берем первый доступный
        if (!$content) {
            $content = collect($this->content)->first();
        }

        return $content;
    }

    public function getTitleAttribute()
    {
        $content = $this->getContentByLanguage();
        return $content['title'] ?? '';
    }

    public function getTextAttribute()
    {
        $content = $this->getContentByLanguage();
        return $content['text'] ?? '';
    }
}
