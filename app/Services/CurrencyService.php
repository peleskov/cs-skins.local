<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CurrencyService
{
    /**
     * Базовый URL для API ЦБ РФ
     */
    protected string $cbrApiUrl = 'https://www.cbr.ru/scripts/XML_daily.asp';
    
    /**
     * Процент наценки к курсам ЦБ РФ
     */
    protected float $markup = 0.02; // 2%

    /**
     * Обновить курсы всех валют
     */
    public function updateExchangeRates(): array
    {
        $results = [];
        
        try {
            // Получаем основную валюту
            $primaryCurrency = Currency::primary();
            
            if (!$primaryCurrency) {
                throw new Exception('Основная валюта не найдена');
            }
            /*
            Log::info('Начинаем обновление курсов валют', [
                'primary_currency' => $primaryCurrency->code
            ]);
            */
            // Получаем курсы от API
            $rates = $this->fetchExchangeRates($primaryCurrency->code);
            
            // Обновляем курсы всех активных валют
            $currencies = Currency::where('is_active', true)
                ->where('is_primary', false)
                ->get();

            foreach ($currencies as $currency) {
                $oldRate = $currency->exchange_rate;
                
                if (isset($rates[$currency->code])) {
                    $newRate = $rates[$currency->code];
                    
                    $currency->update([
                        'exchange_rate' => $newRate
                    ]);
                    
                    $results[] = [
                        'currency' => $currency->code,
                        'old_rate' => $oldRate,
                        'new_rate' => $newRate,
                        'status' => 'updated'
                    ];
                    
                    //Log::info("Обновлён курс {$currency->code}: {$oldRate} → {$newRate}");
                } else {
                    $results[] = [
                        'currency' => $currency->code,
                        'old_rate' => $oldRate,
                        'new_rate' => null,
                        'status' => 'not_found'
                    ];
                    
                    Log::warning("Курс для {$currency->code} не найден в API");
                }
            }
            /*
            Log::info('Обновление курсов завершено', [
                'updated_count' => count(array_filter($results, fn($r) => $r['status'] === 'updated'))
            ]);
            */
        } catch (Exception $e) {
            Log::error('Ошибка при обновлении курсов валют', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }

        return $results;
    }

    /**
     * Получить курсы валют от ЦБ РФ
     */
    protected function fetchExchangeRates(string $baseCurrency): array
    {
        try {
            //Log::info('Запрос курсов валют от ЦБ РФ');
            
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; CurrencyBot/1.0)'
                ])
                ->get($this->cbrApiUrl);

            if (!$response->successful()) {
                throw new Exception("ЦБ РФ API вернул статус {$response->status()}");
            }

            $xmlContent = $response->body();
            
            // Парсим XML
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml === false) {
                throw new Exception('Ошибка парсинга XML от ЦБ РФ');
            }

            // Конвертируем курсы ЦБ РФ в нужный формат
            return $this->convertCbrRatesToArray($xml, $baseCurrency);
            
        } catch (Exception $e) {
            Log::error('Ошибка получения курсов от ЦБ РФ', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Конвертировать курсы ЦБ РФ в массив с нужной базовой валютой
     */
    protected function convertCbrRatesToArray($xml, string $baseCurrency): array
    {
        $rates = [];
        
        // ЦБ РФ всегда дает курсы относительно рубля
        // Добавляем рубль как базу
        $rates['RUB'] = 1.0;
        
        foreach ($xml->Valute as $valute) {
            $charCode = (string) $valute->CharCode;
            $nominal = (float) $valute->Nominal;
            $value = (float) str_replace(',', '.', $valute->Value);
            
            // Курс одной единицы валюты в рублях
            $rateToRub = $value / $nominal;
            
            // Добавляем наценку 2%
            $rateWithMarkup = $rateToRub * (1 + $this->markup);
            
            $rates[$charCode] = $rateWithMarkup;
        }
        
        // Если базовая валюта не RUB, пересчитываем все курсы
        if ($baseCurrency !== 'RUB') {
            if (!isset($rates[$baseCurrency])) {
                throw new Exception("Валюта {$baseCurrency} не найдена в курсах ЦБ РФ");
            }
            
            $baseRate = $rates[$baseCurrency];
            
            // Пересчитываем все курсы через базовую валюту
            foreach ($rates as $code => $rate) {
                $rates[$code] = $rate / $baseRate;
            }
        }
        /*
        Log::info('Получены курсы от ЦБ РФ', [
            'currencies_count' => count($rates),
            'base_currency' => $baseCurrency,
            'markup' => ($this->markup * 100) . '%'
        ]);
        */
        return $rates;
    }


    /**
     * Получить статистику по валютам
     */
    public function getCurrencyStats(): array
    {
        $primary = Currency::primary();
        $active = Currency::where('is_active', true)->count();
        $total = Currency::count();

        return [
            'primary_currency' => $primary ? $primary->code : null,
            'active_currencies' => $active,
            'total_currencies' => $total,
            'last_update' => Currency::where('is_primary', false)
                ->max('updated_at')
        ];
    }
}