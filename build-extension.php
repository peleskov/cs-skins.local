<?php

/**
 * Скрипт сборки браузерного расширения
 * Создаёт расширение для текущего окружения на основе APP_URL
 */

// Загружаем .env файл
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die("Ошибка: файл .env не найден\n");
}

$envContent = file_get_contents($envFile);
preg_match('/APP_URL=(.+)/', $envContent, $matches);

if (!isset($matches[1])) {
    die("Ошибка: APP_URL не найден в .env\n");
}

$appUrl = trim($matches[1]);
$domain = parse_url($appUrl, PHP_URL_HOST);

// Конфигурация
$sourceDir = __DIR__ . '/browser-extension/v2';
$buildDir = __DIR__ . '/public/downloads/extension';
$extensionName = 'CS-SKINS Trading Assistant';
$outputFile = 'cs-skins-extension.zip';

// Создаём директорию для сборок если её нет
if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}

echo "Сборка расширения для домена {$domain}...\n";

// Создаём временную директорию
$tempDir = sys_get_temp_dir() . '/cs-skins-extension-' . time();
mkdir($tempDir, 0755, true);

// Копируем все файлы
copyDirectory($sourceDir, $tempDir);

// Читаем и модифицируем manifest.json
$manifestPath = $tempDir . '/manifest.json';
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
$zipPath = $buildDir . '/' . $outputFile;
createZip($tempDir, $zipPath);

// Удаляем временную директорию
removeDirectory($tempDir);

echo "✓ Создан файл: {$zipPath}\n";
echo "\nГотово! Расширение собрано: {$zipPath}\n";

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
