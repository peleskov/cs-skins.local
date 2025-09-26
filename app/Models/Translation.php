<?php

namespace App\Models;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;

class Translation
{
    protected string $locale;
    protected string $group;
    protected array $translations = [];

    public function __construct(string $locale = 'en', string $group = 'app')
    {
        $this->locale = $locale;
        $this->group = $group;
        $this->loadTranslations();
    }

    /**
     * Загрузить переводы из файла
     */
    protected function loadTranslations(): void
    {
        $path = $this->getFilePath();

        if (File::exists($path)) {
            $this->translations = include $path;
        }
    }

    /**
     * Получить путь к файлу переводов
     */
    protected function getFilePath(): string
    {
        return resource_path("lang/{$this->locale}/{$this->group}.php");
    }

    /**
     * Получить все переводы
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Получить все переводы как плоский массив с точечной нотацией
     */
    public function getFlatTranslations(): array
    {
        return Arr::dot($this->translations);
    }

    /**
     * Установить значение перевода
     */
    public function setTranslation(string $key, string $value): void
    {
        Arr::set($this->translations, $key, $value);
    }

    /**
     * Удалить перевод
     */
    public function removeTranslation(string $key): void
    {
        Arr::forget($this->translations, $key);
    }

    /**
     * Сохранить переводы в файл
     */
    public function save(): bool
    {
        $path = $this->getFilePath();

        // Создаем директорию если не существует
        $dir = dirname($path);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Формируем PHP код
        $content = "<?php\n\nreturn " . $this->arrayToPhpString($this->translations) . ";\n";

        return File::put($path, $content) !== false;
    }

    /**
     * Конвертировать массив в PHP строку
     */
    protected function arrayToPhpString(array $array, int $depth = 1): string
    {
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $indent = str_repeat('    ', $depth);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $indent;

            if ($isAssoc) {
                $result .= "'" . str_replace("'", "\\'", $key) . "' => ";
            }

            if (is_array($value)) {
                $result .= $this->arrayToPhpString($value, $depth + 1);
            } else {
                $result .= "'" . str_replace("'", "\\'", $value) . "'";
            }

            $result .= ",\n";
        }

        $result .= str_repeat('    ', $depth - 1) . "]";

        return $result;
    }

    /**
     * Получить все доступные языки
     */
    public static function getAvailableLocales(): array
    {
        $path = resource_path('lang');
        $directories = File::directories($path);

        return array_map(function($dir) {
            return basename($dir);
        }, $directories);
    }

    /**
     * Получить все доступные группы переводов
     */
    public static function getAvailableGroups(string $locale = 'en'): array
    {
        $path = resource_path("lang/{$locale}");

        if (!File::exists($path)) {
            return [];
        }

        $files = File::files($path);

        return array_map(function($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Получить все переводы для всех языков по ключу
     */
    public static function getTranslationsByKey(string $group, string $key): array
    {
        $locales = self::getAvailableLocales();
        $translations = [];

        foreach ($locales as $locale) {
            $model = new self($locale, $group);
            $flat = $model->getFlatTranslations();
            $translations[$locale] = $flat[$key] ?? '';
        }

        return $translations;
    }

    /**
     * Сохранить переводы для всех языков по ключу
     */
    public static function saveTranslationsByKey(string $group, string $key, array $translations): void
    {
        foreach ($translations as $locale => $value) {
            $model = new self($locale, $group);
            $model->setTranslation($key, $value);
            $model->save();
        }
    }

    /**
     * Получить все переводы для таблицы
     */
    public static function getAllTranslationsForTable(): array
    {
        $locales = self::getAvailableLocales();
        $groups = self::getAvailableGroups($locales[0] ?? 'en');
        $result = [];

        foreach ($groups as $group) {
            $translations = [];

            // Собираем все ключи из всех языков
            $allKeys = [];
            foreach ($locales as $locale) {
                $model = new self($locale, $group);
                $flat = $model->getFlatTranslations();
                $allKeys = array_merge($allKeys, array_keys($flat));
            }
            $allKeys = array_unique($allKeys);

            // Формируем данные для каждого ключа
            foreach ($allKeys as $key) {
                $row = [
                    'id' => $group . '.' . $key,
                    'group' => $group,
                    'key' => $key,
                ];

                foreach ($locales as $locale) {
                    $model = new self($locale, $group);
                    $flat = $model->getFlatTranslations();
                    $row[$locale] = $flat[$key] ?? '';
                }

                $result[] = $row;
            }
        }

        return $result;
    }
}