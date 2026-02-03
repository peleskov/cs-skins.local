<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestBitSkinsApi extends Command
{
    protected $signature = 'bitskins:test
        {--endpoint=search : Endpoint (search, skins, types)}
        {--limit=10 : Количество предметов}
        {--offset=0 : Смещение}
        {--min-price= : Минимальная цена в центах}';

    protected $description = 'Тестовый запрос к BitSkins API для получения предметов CS2';

    public function handle()
    {
        $endpoint = $this->option('endpoint');

        return match($endpoint) {
            'search' => $this->testSearch(),
            'skins' => $this->testSkinsCatalog(),
            'skin_name' => $this->testSkinNameCatalog(),
            'detail' => $this->testSkinDetail(),
            'types' => $this->testTypes(),
            default => $this->error("Unknown endpoint: {$endpoint}")
        };
    }

    private function testSearch(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $minPrice = $this->option('min-price');

        $this->info("Запрос к /market/search/730...");

        $where = [];
        if ($minPrice) {
            $where['price_from'] = (int) $minPrice;
        }

        $searchParams = [
            'order' => [['field' => 'price', 'order' => 'ASC']],
            'offset' => $offset,
            'limit' => $limit,
            'where' => empty($where) ? (object) [] : $where
        ];

        $response = $this->makeRequest('/market/search/730', $searchParams);
        if (!$response) return 1;

        $data = $response->json();
        $this->info("Всего: " . ($data['counter']['total'] ?? 'N/A'));
        $this->info("Получено: " . count($data['list'] ?? []));

        if (!empty($data['list'])) {
            $this->displayItems($data['list']);
        }

        $this->saveResponse($data, 'search');
        return 0;
    }

    private function testSkinsCatalog(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');

        $this->info("Запрос к /market/skins/730 (каталог скинов)...");

        $params = [
            'offset' => $offset,
            'limit' => $limit,
        ];

        $response = $this->makeRequest('/market/skins/730', $params);
        if (!$response) return 1;

        $data = $response->json();
        $this->info("Ответ получен");

        $this->saveResponse($data, 'skins');

        // Показать структуру
        if (isset($data['list']) && !empty($data['list'])) {
            $this->info("Получено скинов: " . count($data['list']));
            $this->displaySkinStructure($data['list'][0]);
        } else {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }

    private function testSkinDetail(): int
    {
        $this->info("Поиск endpoint для деталей скина...");

        // skin_id из предыдущего теста
        $skinId = 140; // StatTrak SSG 08 | Mainframe 001

        $endpoints = [
            '/market/skin/730/' . $skinId,
            '/market/item/730/' . $skinId,
            '/market/detail/730/' . $skinId,
            '/market/skins/730/' . $skinId,
            '/market/get_skin/730/' . $skinId,
            '/skin/730/' . $skinId,
        ];

        foreach ($endpoints as $ep) {
            $this->line("Пробуем: {$ep}");
            $response = $this->makeRequest($ep, [], false);
            if ($response && $response->successful()) {
                $this->info("✅ Найден: {$ep}");
                $data = $response->json();
                $this->saveResponse($data, 'skin_detail');
                $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return 0;
            } else {
                $status = $response ? $response->status() : 'no response';
                $this->line("  ❌ {$status}");
            }
        }

        // Попробуем POST с параметрами
        $this->newLine();
        $this->info("Пробуем POST запросы...");

        $postEndpoints = [
            '/market/skin/730' => ['skin_id' => $skinId],
            '/market/skins/730' => ['where' => ['id' => [$skinId]]],
            '/market/search/730' => ['where' => ['skin_id' => [$skinId]], 'limit' => 1],
        ];

        foreach ($postEndpoints as $ep => $params) {
            $this->line("POST {$ep}");
            $response = $this->makeRequest($ep, $params, false);
            if ($response && $response->successful()) {
                $this->info("✅ Работает: {$ep}");
                $data = $response->json();
                $this->saveResponse($data, 'skin_detail_post');
                $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return 0;
            }
        }

        $this->error("Endpoint для деталей не найден");
        return 1;
    }

    private function testSkinNameCatalog(): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Запрос к /market/search/skin_name (каталог скинов)...");

        $params = [
            'limit' => $limit,
            'where' => [
                'app_id' => 730,
            ]
        ];

        $response = $this->makeRequest('/market/search/skin_name', $params);
        if (!$response) return 1;

        $data = $response->json();
        $this->saveResponse($data, 'skin_name');

        if (is_array($data) && !empty($data)) {
            $this->info("Получено записей: " . count($data));

            // Показать первые несколько
            $headers = ['id', 'name', 'type_id', 'category_id'];
            $rows = [];
            foreach (array_slice($data, 0, 10) as $item) {
                $rows[] = [
                    $item['id'] ?? '-',
                    mb_substr($item['name'] ?? $item['skin_name'] ?? 'N/A', 0, 50),
                    $item['type_id'] ?? '-',
                    $item['category_id'] ?? '-',
                ];
            }
            $this->table($headers, $rows);

            // Показать структуру первого элемента
            $this->newLine();
            $this->info("=== Структура первого элемента ===");
            $this->displaySkinStructure($data[0]);
        } else {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }

    private function testTypes(): int
    {
        $this->info("Запрос справочника типов...");

        // Попробуем разные endpoints
        $endpoints = [
            '/market/types/730',
            '/market/categories/730',
            '/market/filters/730',
            '/market/meta/730',
        ];

        foreach ($endpoints as $ep) {
            $this->line("Пробуем: {$ep}");
            $response = $this->makeRequest($ep, [], false);
            if ($response && $response->successful()) {
                $this->info("OK!");
                $this->saveResponse($response->json(), 'types_' . str_replace('/', '_', $ep));
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return 0;
            }
        }

        $this->error("Справочники не найдены");
        return 1;
    }

    private function makeRequest(string $endpoint, array $params = [], bool $showError = true)
    {
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'origin' => 'https://bitskins.com',
            'referer' => 'https://bitskins.com/market/cs2',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'x-auth-token' => config('services.bitskins.auth_token')
        ])->post(config('services.bitskins.api_base_url') . $endpoint, $params);

        if (!$response->successful() && $showError) {
            $this->error("HTTP {$response->status()}: {$response->body()}");
            return null;
        }

        return $response;
    }

    private function displayItems(array $items): void
    {
        $headers = ['#', 'name', 'price', 'type_id', 'exterior_id', 'skin_id'];
        $rows = [];

        foreach ($items as $i => $item) {
            $rows[] = [
                $i + 1,
                mb_substr($item['name'] ?? 'N/A', 0, 40),
                '$' . number_format(($item['price'] ?? 0) / 1000, 2),
                $item['type_id'] ?? '-',
                $item['exterior_id'] ?? '-',
                $item['skin_id'] ?? '-',
            ];
        }

        $this->table($headers, $rows);
    }

    private function displaySkinStructure(array $item): void
    {
        $this->info("=== Структура скина ===");
        foreach ($item as $key => $value) {
            $display = is_array($value) ? json_encode($value) : $value;
            $this->line("<comment>{$key}</comment>: {$display}");
        }
    }

    private function saveResponse(array $data, string $name): void
    {
        $file = storage_path("logs/bitskins_{$name}.json");
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Сохранено: {$file}");
    }
}
