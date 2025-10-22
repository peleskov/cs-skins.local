<?php

/**
 * Скрипт сборки браузерного расширения
 * Создаёт две версии: dev и prod
 */

// Конфигурация
$sourceDir = __DIR__ . '/browser-extension/v2';
$buildDir = __DIR__ . '/public/downloads/extension';
$extensionName = 'CS-SKINS Trading Assistant';

// Создаём директорию для сборок если её нет
if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}

echo "=== Сборка браузерного расширения ===\n\n";

// Создаём DEV версию
echo "1. Создание DEV версии...\n";
buildExtension(__DIR__ . '/.env', 'cs-skins-extension-dev.zip', true);

echo "\n";

// Создаём PROD версию
echo "2. Создание PROD версии...\n";
buildExtension(__DIR__ . '/.env.prod', 'cs-skins-extension.zip', false);

echo "\n=== Готово! Созданы оба архива ===\n";

/**
 * Создаёт архив расширения для указанного окружения
 */
function buildExtension($envFile, $outputFileName, $isDev) {
    global $sourceDir, $buildDir, $extensionName;

    if (!file_exists($envFile)) {
        echo "⚠️ Файл $envFile не найден, пропускаем\n";
        return;
    }

    // Читаем .env файл
    $envContent = file_get_contents($envFile);
    preg_match('/APP_URL=(.+)/', $envContent, $matches);

    if (!isset($matches[1])) {
        echo "⚠️ APP_URL не найден в $envFile, пропускаем\n";
        return;
    }

    $appUrl = trim($matches[1]);
    $domain = parse_url($appUrl, PHP_URL_HOST);

    echo "   URL: $appUrl\n";

    if ($isDev) {
        // Для dev версии используем исходную папку как есть
        $workingDir = $sourceDir;
        echo "   Режим: Исходная папка (без изменений)\n";
    } else {
        // Для prod версии создаем временную папку и заменяем URL'ы
        $workingDir = sys_get_temp_dir() . '/cs-skins-extension-' . time();
        mkdir($workingDir, 0755, true);

        echo "   Режим: Временная папка с заменой URL\n";

        // Копируем все файлы
        copyDirectory($sourceDir, $workingDir);

        // Заменяем URL'ы в JS файлах
        replaceUrlsInJsFiles($workingDir, $appUrl);
    }

    // Читаем и модифицируем manifest.json
    $manifestPath = $workingDir . '/manifest.json';
    $manifest = json_decode(file_get_contents($manifestPath), true);

    // Обновляем параметры
    $manifest['name'] = $extensionName;
    $manifest['host_permissions'] = [
        'https://steamcommunity.com/*',
        'https://' . $domain . '/*'
    ];

    // Сохраняем обновлённый manifest
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Создаём ZIP архив
    $zipPath = $buildDir . '/' . $outputFileName;
    createZip($workingDir, $zipPath);

    // Удаляем временную директорию только для prod версии
    if (!$isDev) {
        removeDirectory($workingDir);
    }

    echo "   ✓ Создан: $zipPath\n";
}

/**
 * Заменяет URL'ы в JS файлах расширения
 */
function replaceUrlsInJsFiles($dir, $newUrl) {
    $jsFiles = [
        $dir . '/assets/js/index.js',
        $dir . '/assets/js/service-worker.js'
    ];

    $domain = parse_url($newUrl, PHP_URL_HOST);
    $wsUrl = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $newUrl);

    foreach ($jsFiles as $file) {
        if (!file_exists($file)) {
            continue;
        }

        $content = file_get_contents($file);

        // Заменяем API_BASE_URL в index.js
        $content = preg_replace(
            '/this\.API_BASE_URL = [\'"][^\'"]*[\'"];/',
            "this.API_BASE_URL = '$newUrl';",
            $content
        );

        // Заменяем WebSocket URL в service-worker.js
        $content = preg_replace(
            '/new WebSocket\([\'"]wss?:\/\/[^\/]+([^\'"]*)[\'"]/',
            "new WebSocket('$wsUrl$1'",
            $content
        );

        // Заменяем fetch URL в service-worker.js
        $content = preg_replace(
            '/fetch\([\'"]https?:\/\/[^\/]+([^\'"]*)[\'"]/',
            "fetch('$newUrl$1'",
            $content
        );

        file_put_contents($file, $content);
        echo "✓ Обновлен файл: " . basename($file) . "\n";
    }
}

/**
 * Рекурсивное копирование директории
 */
function copyDirectory($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            copy($item, $destPath);
        }
    }
}

/**
 * Создание ZIP архива
 */
function createZip($sourceDir, $zipPath) {
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Не удалось создать ZIP архив: {$zipPath}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $filePath = $item->getPathname();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);

        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
}

/**
 * Удаление директории
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}
