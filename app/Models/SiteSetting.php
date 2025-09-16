<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    const TYPE_STRING = 'string';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('site_settings');
        });

        static::deleted(function () {
            Cache::forget('site_settings');
        });
    }

    public function getTypedValueAttribute()
    {
        return match($this->type) {
            self::TYPE_NUMBER => (float) $this->value,
            self::TYPE_BOOLEAN => (bool) $this->value,
            self::TYPE_JSON => json_decode($this->value, true),
            default => $this->value
        };
    }

    public static function get(string $key, $default = null)
    {
        $settings = Cache::remember('site_settings', 3600, function () {
            return static::all()->keyBy('key');
        });

        $setting = $settings->get($key);

        if (!$setting) {
            return $default;
        }

        return $setting->typed_value;
    }

    public static function set(string $key, $value, string $type = self::TYPE_STRING, string $description = null): void
    {
        $formattedValue = match($type) {
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            self::TYPE_JSON => json_encode($value),
            default => (string) $value
        };

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $formattedValue,
                'type' => $type,
                'description' => $description
            ]
        );
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}