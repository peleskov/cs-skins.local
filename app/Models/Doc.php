<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Doc extends Model
{
    protected $fillable = [
        'title',
        'content',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($doc) {
            if (!$doc->slug) {
                $doc->slug = Str::slug($doc->title);
            }
        });
        
        static::updating(function ($doc) {
            if (!$doc->slug) {
                $doc->slug = Str::slug($doc->title);
            }
        });
    }
}
