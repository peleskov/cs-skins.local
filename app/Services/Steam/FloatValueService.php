<?php

namespace App\Services\Steam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FloatValueService
{
    private const CSFLOAT_API_URL = 'https://api.csfloat.com/';
    private const TIMEOUT = 30;

    /**
     * Получить float данные предмета через CSFloat API
     */
    public function getFloatData(string $inspectUrl): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://csfloat.com',
                    'Referer' => 'https://csfloat.com/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
                ])
                ->get(self::CSFLOAT_API_URL, [
                    'url' => $inspectUrl
                ]);

            if (!$response->successful()) {
                Log::warning('CSFloat API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'inspect_url' => $inspectUrl
                ]);
                return null;
            }

            $data = $response->json();

            if (!isset($data['iteminfo'])) {
                Log::warning('CSFloat API returned invalid response', [
                    'response' => $data,
                    'inspect_url' => $inspectUrl
                ]);
                return null;
            }

            return $this->normalizeFloatData($data['iteminfo']);

        } catch (\Exception $e) {
            Log::error('Error fetching float data from CSFloat', [
                'error' => $e->getMessage(),
                'inspect_url' => $inspectUrl
            ]);
            return null;
        }
    }

    /**
     * Нормализовать данные от CSFloat API в формат для нашей БД
     */
    private function normalizeFloatData(array $itemInfo): array
    {
        return [
            'float_value' => $itemInfo['floatvalue'] ?? null,
            'float_min' => $itemInfo['min'] ?? null,
            'float_max' => $itemInfo['max'] ?? null,
            'paint_index' => $itemInfo['paintindex'] ?? null,
            'def_index' => $itemInfo['defindex'] ?? null,
            'csfloat_id' => $itemInfo['floatid'] ?? null,
            'pattern_index' => $itemInfo['paintseed'] ?? null,
            'float_fetched_at' => now(),
        ];
    }

    /**
     * Извлечь asset ID из inspect URL (используется как csfloat_id)
     */
    public function extractAssetIdFromInspectUrl(string $inspectUrl): ?string
    {
        // Паттерн для извлечения A{assetid} из inspect URL
        if (preg_match('/A(\d+)/', $inspectUrl, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Проверить, не превышен ли rate limit CSFloat
     */
    public function checkRateLimit(): bool
    {
        // Простая проверка - делаем тестовый запрос
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Origin' => 'https://csfloat.com',
                    'Referer' => 'https://csfloat.com/',
                ])
                ->get(self::CSFLOAT_API_URL . '?url=invalid');
            
            // Если получили 429 - превышен лимит
            return $response->status() !== 429;
        } catch (\Exception $e) {
            return true; // Если ошибка - считаем что лимит не превышен
        }
    }
}