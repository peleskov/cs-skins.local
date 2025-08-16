<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkinScreenshotService
{

    /**
     * Обрабатывает listing и получает скриншоты
     */
    public function processListing($listing): bool
    {
        try {
            // Проверяем наличие market_hash_name
            if (empty($listing->market_hash_name)) {
                Log::warning('No market_hash_name found', ['listing_id' => $listing->id]);
                return false;
            }

            // Делаем запрос к API BitSkins
            $assetId = $this->getAssetIdFromApi($listing->market_hash_name);
            if (!$assetId) {
                Log::warning('No asset_id found in API', ['skin_name' => $listing->market_hash_name]);
                return false;
            }

            // Генерируем URL изображений
            $imageUrls = $this->generateImageUrls($assetId);

            // Скачиваем и обрабатываем изображения (используем наш steam_asset_id для именования файлов)
            $success = $this->downloadAndProcessImages($imageUrls, $listing->steam_asset_id);
            
            // Сохраняем результат: 1 если получили хотя бы одно изображение, 0 если не получили
            $listing->screenshots = $success ? 1 : 0;
            $listing->save();

            return true;

        } catch (Exception $e) {
            Log::error('Error processing listing screenshots', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


    /**
     * Получает asset_id из API BitSkins
     */
    private function getAssetIdFromApi(string $skinName): ?string
    {
        $searchParams = [
            "order" => [
                ["field" => "price", "order" => "ASC"]
            ],
            "offset" => 0,
            "limit" => 30,
            "where" => [
                "skin_name" => "%" . str_replace(' ', '%', $skinName) . "%"
            ]
        ];

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'origin' => 'https://bitskins.com',
            'referer' => 'https://bitskins.com/market/cs2',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'x-auth-token' => config('services.bitskins.auth_token')
        ])->post(config('services.bitskins.api_base_url') . '/market/search/730', $searchParams);

        if (!$response->successful()) {
            Log::error('BitSkins API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();
        
        // Берем первый asset_id из ответа
        if (!empty($data['list'][0]['asset_id'])) {
            return $data['list'][0]['asset_id'];
        }

        return null;
    }

    /**
     * Генерирует URL изображений по алгоритму BitSkins
     */
    private function generateImageUrls(string $assetId): array
    {
        $stringToHash = "bs1-{$assetId}";
        $hash = md5($stringToHash);
        $folder = substr($hash, 0, 2);
        
        return [
            'front' => config('services.bitskins.image_base_url') . "/{$folder}/{$hash}-front.webp",
            'back' => config('services.bitskins.image_base_url') . "/{$folder}/{$hash}-back.webp"
        ];
    }

    /**
     * Скачивает и обрабатывает изображения
     */
    private function downloadAndProcessImages(array $imageUrls, string $assetId): bool
    {
        $hash = md5($assetId);
        $baseDir = 'images/skins';
        $hasSuccessfulDownload = false;

        foreach ($imageUrls as $type => $url) {
            // Имена файлов на основе hash от asset_id
            $originalFilename = "{$baseDir}/{$hash}_{$type}_original.webp";
            $processedFilename = "{$baseDir}/{$hash}_{$type}.webp";
            $originalFullPath = public_path($originalFilename);
            $processedFullPath = public_path($processedFilename);

            // Создаем директорию
            $directory = dirname($originalFullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Проверяем, может обработанный файл уже существует
            if (file_exists($processedFullPath)) {
                $hasSuccessfulDownload = true;
                continue;
            }

            // Скачиваем изображение
            $response = Http::get($url);
            
            if ($response->successful()) {
                // Сохраняем оригинал
                file_put_contents($originalFullPath, $response->body());
                
                // Обрабатываем изображение
                $this->processImage($originalFullPath, $processedFullPath);
                
                $hasSuccessfulDownload = true;
                /*
                Log::info('Image downloaded and processed', [
                    'type' => $type,
                    'asset_id' => $assetId,
                    'hash' => $hash,
                    'processed_path' => $processedFilename
                ]);
                */
            } else {
                Log::error('Failed to download image', [
                    'type' => $type,
                    'url' => $url,
                    'asset_id' => $assetId,
                    'status' => $response->status()
                ]);
            }
        }

        return $hasSuccessfulDownload;
    }

    /**
     * Обрабатывает изображение с логотипами
     */
    private function processImage(string $originalPath, string $processedPath): bool
    {
        try {
            $processor = new ImageProcessor();
            $logoPath = storage_path('app/screenshots/logo.png');
            $fullLogoPath = storage_path('app/screenshots/full_logo.png');
            
            return $processor->processImage($originalPath, $processedPath, $logoPath, $fullLogoPath);
        } catch (Exception $e) {
            Log::error('Image processing failed', [
                'original' => $originalPath,
                'processed' => $processedPath,
                'error' => $e->getMessage()
            ]);
            
            // Если обработка не удалась, копируем оригинал
            return copy($originalPath, $processedPath);
        }
    }

    /**
     * Генерирует URLs для скриншотов на основе asset_id
     */
    public static function generateScreenshotUrls(string $assetId): array
    {
        $hash = md5($assetId);
        $baseUrl = config('app.url') . '/images/skins';
        
        return [
            'front' => "{$baseUrl}/{$hash}_front.webp",
            'back' => "{$baseUrl}/{$hash}_back.webp"
        ];
    }
}

/**
 * Класс для обработки изображений с логотипами
 */
class ImageProcessor
{
    private $canvas_width = 1000;
    private $canvas_height = 600;
    private $margin = 60;
    
    public function processImage($inputPath, $outputPath, $logoPath, $fullLogoPath)
    {
        // Проверяем существование файлов
        if (!file_exists($inputPath)) {
            throw new Exception("Input image not found: $inputPath");
        }
        if (!file_exists($logoPath)) {
            throw new Exception("Logo not found: $logoPath");
        }
        if (!file_exists($fullLogoPath)) {
            throw new Exception("Full logo not found: $fullLogoPath");
        }
        
        // Создаем холст
        $canvas = imagecreatetruecolor($this->canvas_width, $this->canvas_height);
        
        // Заливаем белым фоном
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        
        // Создаем фон из логотипов
        $this->createTiledBackground($canvas, $logoPath);
        
        // Загружаем и размещаем основное изображение
        $this->placeMainImage($canvas, $inputPath);
        
        // Размещаем полное лого в левом верхнем углу
        $this->placeFullLogo($canvas, $fullLogoPath);
        
        // Создаем директорию если не существует
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Сохраняем результат
        $result = imagewebp($canvas, $outputPath, 95);
        
        imagedestroy($canvas);
        
        if (!$result) {
            throw new Exception("Failed to save image: $outputPath");
        }
        
        return true;
    }
    
    private function createTiledBackground($canvas, $logoPath)
    {
        // Загружаем PNG лого
        $logoImage = imagecreatefrompng($logoPath);
        
        if (!$logoImage) {
            throw new Exception("Failed to load logo PNG");
        }
        
        // Используем оригинальный размер лого
        $logoWidth = imagesx($logoImage);
        $logoHeight = imagesy($logoImage);
        
        // Включаем альфа-канал для корректного отображения прозрачности
        imagealphablending($logoImage, true);
        imagesavealpha($logoImage, true);
        
        // Замащиваем фон логотипами по диагонали от центра
        $centerX = $this->canvas_width / 2;
        $centerY = $this->canvas_height / 2;
        $stepX = $logoWidth * 1.5; // шаг по X
        $stepY = $logoHeight * 1.5; // шаг по Y
        $offsetX = $stepX / 2; // смещение для четных рядов
        
        // Вычисляем количество шагов от центра до краев
        $stepsLeft = ceil($centerX / $stepX) + 1;
        $stepsRight = ceil($centerX / $stepX) + 1;
        $stepsUp = ceil($centerY / $stepY) + 1;
        $stepsDown = ceil($centerY / $stepY) + 1;
        
        for ($row = -$stepsUp; $row <= $stepsDown; $row++) {
            $y = $centerY + ($row * $stepY);
            
            // Смещение для четных рядов
            $currentOffsetX = ($row % 2 == 1) ? $offsetX : 0;
            
            for ($col = -$stepsLeft; $col <= $stepsRight; $col++) {
                $x = $centerX + ($col * $stepX) + $currentOffsetX;
                
                imagecopy($canvas, $logoImage, $x - $logoWidth/2, $y - $logoHeight/2, 0, 0, $logoWidth, $logoHeight);
            }
        }
        
        imagedestroy($logoImage);
    }
    
    private function placeMainImage($canvas, $inputPath)
    {
        // Загружаем изображение
        $sourceImage = imagecreatefromwebp($inputPath);
        if (!$sourceImage) {
            throw new Exception("Failed to load main image: $inputPath");
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Вычисляем доступную область (с отступами)
        $availableWidth = $this->canvas_width - (2 * $this->margin);
        $availableHeight = $this->canvas_height - (2 * $this->margin);
        
        // Вычисляем масштаб для сохранения пропорций
        $scaleX = $availableWidth / $sourceWidth;
        $scaleY = $availableHeight / $sourceHeight;
        $scale = min($scaleX, $scaleY);
        
        // Новые размеры
        $newWidth = (int)($sourceWidth * $scale);
        $newHeight = (int)($sourceHeight * $scale);
        
        // Позиция для центрирования
        $destX = $this->margin + (($availableWidth - $newWidth) / 2);
        $destY = $this->margin + (($availableHeight - $newHeight) / 2);
        
        // Размещаем изображение
        imagecopyresampled(
            $canvas, $sourceImage,
            $destX, $destY,
            0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );
        
        imagedestroy($sourceImage);
    }
    
    private function placeFullLogo($canvas, $fullLogoPath)
    {
        // Загружаем PNG логотип
        $fullLogo = imagecreatefrompng($fullLogoPath);
        if (!$fullLogo) {
            throw new Exception("Failed to load full logo PNG: $fullLogoPath");
        }
        
        // Получаем размеры логотипа
        $logoWidth = imagesx($fullLogo);
        $logoHeight = imagesy($fullLogo);
        
        // Включаем поддержку альфа-канала
        imagealphablending($fullLogo, true);
        imagesavealpha($fullLogo, true);
        
        // Размещаем в левом верхнем углу с отступом
        imagecopy($canvas, $fullLogo, 20, 20, 0, 0, $logoWidth, $logoHeight);
        
        imagedestroy($fullLogo);
    }
}