<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class Translation extends Model
{
    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
    ];

    protected $casts = [
        'group' => 'string',
        'key' => 'string',
        'locale' => 'string',
        'value' => 'string',
    ];

    /**
     * События модели для синхронизации с файлами
     */
    protected static function boot()
    {
        parent::boot();

        // При создании/обновлении записи - синхронизируем с файлом
        static::created(function ($translation) {
            $translation->syncToFile();
        });

        static::updated(function ($translation) {
            $translation->syncToFile();
        });

        // При удалении записи - удаляем из файла
        static::deleted(function ($translation) {
            $translation->removeFromFile();
        });
    }

    /**
     * Синхронизировать запись в файл перевода
     */
    public function syncToFile(): void
    {
        $filePath = resource_path("lang/{$this->locale}/{$this->group}.php");

        // Создаем директорию если не существует
        $dir = dirname($filePath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Загружаем существующие переводы
        $translations = [];
        if (File::exists($filePath)) {
            $translations = include $filePath;
        }

        // Устанавливаем новое значение
        data_set($translations, $this->key, $this->value);

        // Сохраняем файл
        $content = "<?php\n\nreturn ".var_export($translations, true).";\n";
        File::put($filePath, $content);

        // Очищаем кеш переводов Laravel
        Cache::forget("translation.{$this->locale}.{$this->group}");
    }

    /**
     * Удалить запись из файла перевода
     */
    public function removeFromFile(): void
    {
        $filePath = resource_path("lang/{$this->locale}/{$this->group}.php");

        if (! File::exists($filePath)) {
            return;
        }

        // Загружаем переводы
        $translations = include $filePath;

        // Удаляем ключ
        data_forget($translations, $this->key);

        // Если файл стал пустым - удаляем его, иначе сохраняем
        if (empty($translations)) {
            File::delete($filePath);
        } else {
            $content = "<?php\n\nreturn ".var_export($translations, true).";\n";
            File::put($filePath, $content);
        }

        // Очищаем кеш
        Cache::forget("translation.{$this->locale}.{$this->group}");
    }

    /**
     * Получить все доступные языки из файловой структуры
     */
    public static function getAvailableLocales(): array
    {
        $langPath = resource_path('lang');

        if (! File::exists($langPath)) {
            return [];
        }

        $directories = File::directories($langPath);

        return array_map(function ($dir) {
            return basename($dir);
        }, $directories);
    }

    /**
     * Получить все доступные группы для определенного языка
     */
    public static function getAvailableGroups(string $locale = 'en'): array
    {
        $langPath = resource_path("lang/{$locale}");

        if (! File::exists($langPath)) {
            return [];
        }

        $files = File::files($langPath);

        return array_map(function ($file) {
            return pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Синхронизировать все переводы из файлов в БД
     */
    public static function syncFromFiles(): int
    {
        $locales = static::getAvailableLocales();
        $synced = 0;

        foreach ($locales as $locale) {
            $groups = static::getAvailableGroups($locale);

            foreach ($groups as $group) {
                $synced += static::syncGroupFromFile($locale, $group);
            }
        }

        // Удаляем записи из БД, которых нет в файлах
        static::cleanupDeletedTranslations();

        return $synced;
    }

    /**
     * Синхронизировать группу переводов из файла
     */
    protected static function syncGroupFromFile(string $locale, string $group): int
    {
        $filePath = resource_path("lang/{$locale}/{$group}.php");

        if (! File::exists($filePath)) {
            return 0;
        }

        $translations = include $filePath;
        $synced = 0;

        static::syncNestedArray($translations, '', $locale, $group, $synced);

        return $synced;
    }

    /**
     * Рекурсивно синхронизировать вложенный массив переводов
     */
    protected static function syncNestedArray(array $array, string $prefix, string $locale, string $group, int &$synced): void
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                static::syncNestedArray($value, $fullKey, $locale, $group, $synced);
            } else {
                static::updateOrCreate([
                    'group' => $group,
                    'key' => $fullKey,
                    'locale' => $locale,
                ], [
                    'value' => $value,
                ]);

                $synced++;
            }
        }
    }

    /**
     * Удалить записи из БД, которых нет в файлах
     */
    protected static function cleanupDeletedTranslations(): void
    {
        $locales = static::getAvailableLocales();
        $existingKeys = collect();

        // Собираем все существующие ключи из файлов
        foreach ($locales as $locale) {
            $groups = static::getAvailableGroups($locale);

            foreach ($groups as $group) {
                $filePath = resource_path("lang/{$locale}/{$group}.php");

                if (File::exists($filePath)) {
                    $translations = include $filePath;
                    static::collectKeys($translations, '', $locale, $group, $existingKeys);
                }
            }
        }

        // Получаем ID записей, которые существуют в файлах
        $idsToKeep = static::whereIn(
            \DB::raw("CONCAT(locale, ':', `group`, ':', `key`)"),
            $existingKeys->toArray()
        )->pluck('id')->toArray();

        // Удаляем записи из БД, которых нет в файлах
        static::whereNotIn('id', $idsToKeep)->delete();
    }

    /**
     * Собрать все ключи из массива переводов
     */
    protected static function collectKeys(array $array, string $prefix, string $locale, string $group, &$existingKeys): void
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                static::collectKeys($value, $fullKey, $locale, $group, $existingKeys);
            } else {
                $existingKeys->push("{$locale}:{$group}:{$fullKey}");
            }
        }
    }
}
