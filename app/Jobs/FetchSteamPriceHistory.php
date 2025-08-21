<?php

namespace App\Jobs;

use App\Models\SteamMarketItem;
use App\Models\SteamPriceHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchSteamPriceHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $marketHashName;
    protected $steamMarketItem;
    
    // Максимум 2 попытки
    public $tries = 2;
    
    // Задержка между попытками - 60 секунд
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(string $marketHashName)
    {
        $this->marketHashName = $marketHashName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Получаем или создаём запись в справочнике
            $this->steamMarketItem = SteamMarketItem::firstOrCreate(
                ['market_hash_name' => $this->marketHashName]
            );

            // Если item_nameid уже есть и обновление было недавно - пропускаем
            if ($this->steamMarketItem->item_nameid && !$this->steamMarketItem->needsPriceUpdate()) {
                return;
            }

            // Используем простой curl вместо Guzzle - он работает!
            $url = 'https://steamcommunity.com/market/listings/730/' . rawurlencode($this->marketHashName);
            $command = "curl -s -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36' '" . escapeshellarg($url) . "'";
            
            $html = shell_exec($command);
            
            if (empty($html)) {
                throw new \Exception("Failed to fetch Steam page - no response");
            }
            
            // Проверяем, торгуется ли предмет на маркете
            if (strpos($html, 'There are no listings for this item') !== false) {
                // Предмет не торгуется - просто завершаем
                return;
            }

            // Извлекаем item_nameid если его ещё нет
            if (!$this->steamMarketItem->item_nameid) {
                $this->extractItemNameId($html);
            }

            // Извлекаем историю цен
            $this->extractPriceHistory($html);

            // Обновляем время последнего обновления
            $this->steamMarketItem->update([
                'last_price_update' => now()
            ]);

        } catch (\Exception $e) {
            // Если это вторая попытка - просто завершаем без ошибки
            if ($this->attempts() >= 2) {
                return;
            }
            
            // Первая попытка - пробуем еще раз
            throw $e;
        }
    }

    /**
     * Извлекаем item_nameid из HTML страницы
     */
    protected function extractItemNameId(string $html): void
    {
        // Ищем Market_LoadOrderSpread( 123456 );
        if (preg_match('/Market_LoadOrderSpread\s*\(\s*(\d+)\s*\)/', $html, $matches)) {
            $this->steamMarketItem->update([
                'item_nameid' => $matches[1]
            ]);
        }
    }

    /**
     * Извлекаем историю цен из переменной line1
     */
    protected function extractPriceHistory(string $html): void
    {
        // Ищем var line1=[...]; с более гибким паттерном
        if (!preg_match('/var\s+line1\s*=\s*(\[.*?\]);/s', $html, $matches)) {
            // Пробуем альтернативный паттерн
            if (!preg_match('/\bline1\s*=\s*(\[.*?\]);/s', $html, $matches)) {
                // Нет данных о ценах - просто выходим
                return;
            }
        }

        $jsonData = $matches[1];
        
        // Декодируем JSON
        $priceData = json_decode($jsonData, true);
        
        if (!$priceData) {
            throw new \Exception("Failed to parse price history JSON");
        }

        // Берём только последние 365 дней
        $oneYearAgo = now()->subDays(365);
        $filteredData = [];

        foreach ($priceData as $point) {
            // Формат: ["Aug 18 2024 01: +0", 39.73, "98"]
            $dateStr = $point[0];
            $price = floatval($point[1]);
            $volume = intval($point[2]);

            // Парсим дату
            $date = $this->parseDate($dateStr);
            
            // Отладочное логирование
            /*
            Log::info('Parsing date', [
                'original' => $dateStr,
                'cleaned' => preg_replace('/(\d{2}):.*$/', '$1:00', $dateStr),
                'parsed_date' => $date ? $date->toDateTimeString() : 'NULL',
                'price' => $price,
                'volume' => $volume
            ]);
            */
            if ($date && $date >= $oneYearAgo) {
                $dateKey = $date->format('Y-m-d');
                
                // Группируем по дням (берём среднее)
                if (!isset($filteredData[$dateKey])) {
                    $filteredData[$dateKey] = [
                        'prices' => [],
                        'volumes' => []
                    ];
                }
                
                $filteredData[$dateKey]['prices'][] = $price;
                $filteredData[$dateKey]['volumes'][] = $volume;
            }
        }

        // Логируем результаты группировки
        /*
        Log::info('Grouped price data', [
            'market_hash_name' => $this->marketHashName,
            'total_groups' => count($filteredData),
            'groups' => array_map(function($data) {
                return [
                    'price_count' => count($data['prices']),
                    'avg_price' => array_sum($data['prices']) / count($data['prices']),
                    'total_volume' => array_sum($data['volumes'])
                ];
            }, $filteredData)
        ]);
        */
        // Сохраняем в БД
        foreach ($filteredData as $dateKey => $data) {
            $avgPrice = array_sum($data['prices']) / count($data['prices']);
            $totalVolume = array_sum($data['volumes']);
            /*
            Log::info('Saving to DB', [
                'date' => $dateKey,
                'avg_price' => $avgPrice,
                'total_volume' => $totalVolume,
                'price_points' => count($data['prices'])
            ]);
            /*
            SteamPriceHistory::updateOrCreate(
                [
                    'steam_market_item_id' => $this->steamMarketItem->id,
                    'date' => $dateKey
                ],
                [
                    'price' => $avgPrice,
                    'volume' => $totalVolume
                ]
            );
        }
    }

    /**
     * Парсим дату из формата Steam
     */
    protected function parseDate(string $dateStr): ?\Carbon\Carbon
    {
        try {
            // Убираем лишние символы после времени
            $dateStr = preg_replace('/(\d{2}):.*$/', '$1:00', $dateStr);
            
            // Пробуем разные форматы
            $formats = [
                'M j Y H:i',
                'F j Y H:i',
                'M d Y H:i',
                'F d Y H:i'
            ];

            foreach ($formats as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, $dateStr);
                } catch (\Exception $e) {
                    continue;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}