<?php

namespace App\Console\Commands;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class PackExtensionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'extension:pack 
                          {--browser=chrome : Target browser (chrome, firefox, all)}
                          {--output= : Output directory (default: storage/app/extensions)}
                          {--ext-version= : Extension version (auto-increment if not specified)}
                          {--clean : Clean output directory before packing}';

    /**
     * The console command description.
     */
    protected $description = 'Pack browser extension for distribution';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Начинаем упаковку браузерного расширения...');

        // Параметры команды
        $browser = $this->option('browser');
        $outputDir = $this->option('output') ?: storage_path('app/extensions');
        $version = $this->option('ext-version');
        $clean = $this->option('clean');

        // Проверяем существование папки расширения
        $extensionPath = base_path('browser-extension');
        if (!File::isDirectory($extensionPath)) {
            $this->error('❌ Папка browser-extension не найдена!');
            return 1;
        }

        // Создаем выходную директорию
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("📁 Создана директория: {$outputDir}");
        }

        // Очищаем выходную директорию если нужно
        if ($clean) {
            File::cleanDirectory($outputDir);
            $this->info('🧹 Выходная директория очищена');
        }

        // Определяем браузеры для упаковки
        $browsers = $browser === 'all' ? ['chrome', 'firefox'] : [$browser];

        foreach ($browsers as $targetBrowser) {
            $this->packForBrowser($targetBrowser, $extensionPath, $outputDir, $version);
        }

        $this->info('✅ Упаковка завершена!');
        $this->info("📦 Архивы сохранены в: {$outputDir}");

        return 0;
    }

    /**
     * Упаковка для конкретного браузера
     */
    private function packForBrowser(string $browser, string $sourcePath, string $outputDir, ?string $version): void
    {
        $this->info("📦 Упаковка для {$browser}...");

        // Создаем временную директорию
        $tempDir = storage_path('app/temp/extension-' . uniqid());
        File::makeDirectory($tempDir, 0755, true);

        try {
            // Копируем файлы расширения
            File::copyDirectory($sourcePath, $tempDir);

            // Адаптируем для браузера
            $this->adaptForBrowser($browser, $tempDir);

            // Обновляем версию если указана
            if ($version) {
                $this->updateVersion($tempDir, $version);
            }

            // Получаем текущую версию из manifest
            $currentVersion = $this->getVersionFromManifest($tempDir);

            // Создаем архив
            $archiveName = "cs-skins-pro-extension-{$browser}-v{$currentVersion}.zip";
            $archivePath = $outputDir . '/' . $archiveName;

            if ($this->createZipArchive($tempDir, $archivePath)) {
                $this->info("✅ {$browser}: {$archiveName}");
                
                // Показываем размер файла
                $size = File::size($archivePath);
                $this->line("   Размер: " . $this->formatBytes($size));
            } else {
                $this->error("❌ Ошибка создания архива для {$browser}");
            }

        } finally {
            // Удаляем временную директорию
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Адаптация для конкретного браузера
     */
    private function adaptForBrowser(string $browser, string $path): void
    {
        $manifestPath = $path . '/manifest.json';
        
        if (!File::exists($manifestPath)) {
            $this->error("❌ Файл manifest.json не найден в {$path}");
            return;
        }

        $manifest = json_decode(File::get($manifestPath), true);

        switch ($browser) {
            case 'firefox':
                $this->adaptForFirefox($manifest, $path);
                break;
            case 'chrome':
                $this->adaptForChrome($manifest, $path);
                break;
        }

        // Сохраняем обновленный manifest
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("   Адаптирован manifest.json для {$browser}");
    }

    /**
     * Адаптация для Firefox
     */
    private function adaptForFirefox(array &$manifest, string $path): void
    {
        // Конвертируем в Manifest v2 для Firefox
        $manifest['manifest_version'] = 2;

        // Заменяем service worker на background script
        if (isset($manifest['background']['service_worker'])) {
            $manifest['background'] = [
                'scripts' => ['background/background.js'],
                'persistent' => false
            ];

            // Создаем адаптер для Firefox
            $backgroundContent = $this->createFirefoxBackgroundAdapter();
            File::put($path . '/background/background.js', $backgroundContent);
        }

        // Добавляем browser_specific_settings для Firefox
        $manifest['browser_specific_settings'] = [
            'gecko' => [
                'id' => 'cs-skins-pro@extension.local',
                'strict_min_version' => '91.0'
            ]
        ];

        // Конвертируем permissions
        if (isset($manifest['host_permissions'])) {
            $manifest['permissions'] = array_merge(
                $manifest['permissions'] ?? [],
                $manifest['host_permissions']
            );
            unset($manifest['host_permissions']);
        }

        // Заменяем action на browser_action
        if (isset($manifest['action'])) {
            $manifest['browser_action'] = $manifest['action'];
            unset($manifest['action']);
        }
    }

    /**
     * Адаптация для Chrome
     */
    private function adaptForChrome(array &$manifest, string $path): void
    {
        // Chrome уже использует оригинальный Manifest v3
        // Убираем ключ для unpacked расширений (он нужен только для Web Store)
        unset($manifest['key']);
    }

    /**
     * Создание адаптера background script для Firefox
     */
    private function createFirefoxBackgroundAdapter(): string
    {
        return <<<'JS'
// Firefox Background Script Adapter
// Конвертирует Service Worker API в background script API

// Импортируем основной service worker код
importScripts('service-worker.js');

// Адаптер для Firefox API
if (typeof browser !== 'undefined' && !chrome.runtime) {
    // Полифилл Chrome API для Firefox
    window.chrome = browser;
}

// Обработка специфичных для Firefox событий
browser.runtime.onInstalled.addListener((details) => {
    console.log('Firefox extension installed/updated');
});

console.log('Firefox background script loaded');
JS;
    }

    /**
     * Обновление версии в manifest.json
     */
    private function updateVersion(string $path, string $version): void
    {
        $manifestPath = $path . '/manifest.json';
        $manifest = json_decode(File::get($manifestPath), true);
        
        $oldVersion = $manifest['version'] ?? '1.0.0';
        $manifest['version'] = $version;
        
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("   Версия обновлена: {$oldVersion} → {$version}");
    }

    /**
     * Получение версии из manifest.json
     */
    private function getVersionFromManifest(string $path): string
    {
        $manifestPath = $path . '/manifest.json';
        $manifest = json_decode(File::get($manifestPath), true);
        
        return $manifest['version'] ?? '1.0.0';
    }

    /**
     * Создание ZIP архива
     */
    private function createZipArchive(string $sourcePath, string $archivePath): bool
    {
        $zip = new ZipArchive();
        
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        // Получаем все файлы
        $files = File::allFiles($sourcePath);
        
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            
            // Пропускаем нежелательные файлы
            if ($this->shouldSkipFile($relativePath)) {
                continue;
            }
            
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        // Добавляем пустые директории
        $directories = $this->getEmptyDirectories($sourcePath);
        foreach ($directories as $dir) {
            $zip->addEmptyDir($dir);
        }

        return $zip->close();
    }

    /**
     * Проверка, нужно ли пропустить файл
     */
    private function shouldSkipFile(string $relativePath): bool
    {
        $skipPatterns = [
            '.git/',
            '.DS_Store',
            'Thumbs.db',
            '*.log',
            '*.tmp',
            'node_modules/',
            '.env',
            '*.zip'
        ];

        foreach ($skipPatterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Получение пустых директорий
     */
    private function getEmptyDirectories(string $path): array
    {
        $directories = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && !in_array($file->getBasename(), ['.', '..'])) {
                $relativePath = substr($file->getPathname(), strlen($path) + 1);
                
                // Проверяем, пуста ли директория
                $contents = scandir($file->getPathname());
                if (count($contents) === 2) { // только . и ..
                    $directories[] = $relativePath;
                }
            }
        }

        return $directories;
    }

    /**
     * Форматирование размера файла
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}