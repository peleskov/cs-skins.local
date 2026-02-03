<?php

namespace App\Console\Commands;

use App\Models\VirtualItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportVirtualItemsCommand extends Command
{
    protected $signature = 'virtual-items:import
                            {--fresh : Очистить таблицу перед импортом}
                            {--no-download : Использовать локальный файл без скачивания}';

    protected $description = 'Импорт виртуальных предметов из GitHub JSON';

    private const JSON_URL = 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/skins_not_grouped.json';
    private const LOCAL_FILE = 'cs2-skins.json';

    public function handle(): int
    {
        // 1. Скачиваем JSON если нужно
        if (!$this->option('no-download')) {
            $this->info('Скачивание JSON...');

            $response = Http::timeout(120)->get(self::JSON_URL);

            if (!$response->successful()) {
                $this->error('Не удалось скачать файл: ' . $response->status());
                return 1;
            }

            Storage::put(self::LOCAL_FILE, $response->body());
            $this->info('Файл сохранён: storage/app/' . self::LOCAL_FILE);
        }

        // 2. Читаем JSON
        if (!Storage::exists(self::LOCAL_FILE)) {
            $this->error('Файл не найден: storage/app/' . self::LOCAL_FILE);
            return 1;
        }

        $json = Storage::get(self::LOCAL_FILE);
        $items = json_decode($json, true);

        if (!is_array($items)) {
            $this->error('Ошибка парсинга JSON');
            return 1;
        }

        $this->info('Загружено ' . count($items) . ' предметов из JSON');

        // 3. Очищаем таблицу если --fresh
        if ($this->option('fresh')) {
            $this->warn('Очистка таблицы virtual_items...');
            VirtualItem::truncate();
        }

        // 4. Импортируем
        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            try {
                $data = $this->mapItemData($item);

                if (!$data['market_hash_name']) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $existing = VirtualItem::where('market_hash_name', $data['market_hash_name'])->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    VirtualItem::create($data);
                    $created++;
                }
            } catch (\Exception $e) {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Создано: {$created}");
        $this->info("Обновлено: {$updated}");
        $this->info("Пропущено: {$skipped}");

        return 0;
    }

    private function mapItemData(array $item): array
    {
        return [
            'market_hash_name' => $item['market_hash_name'] ?? null,
            'name' => $item['name'] ?? null,
            'weapon_type' => $item['weapon']['name'] ?? null,
            'skin_name' => $item['pattern']['name'] ?? null,
            'quality' => $item['wear']['name'] ?? null,
            'rarity' => $this->mapRarity($item['rarity']['name'] ?? null),
            'rarity_color' => $item['rarity']['color'] ?? null,
            'image_url' => $item['image'] ?? null,
            'is_stattrak' => $item['stattrak'] ?? false,
            'is_souvenir' => $item['souvenir'] ?? false,
            'is_active' => true,
        ];
    }

    private function mapRarity(?string $rarity): ?string
    {
        // Маппинг редкостей из JSON в наши константы
        $map = [
            'Consumer Grade' => VirtualItem::RARITY_CONSUMER,
            'Industrial Grade' => VirtualItem::RARITY_INDUSTRIAL,
            'Mil-Spec Grade' => VirtualItem::RARITY_MIL_SPEC,
            'Restricted' => VirtualItem::RARITY_RESTRICTED,
            'Classified' => VirtualItem::RARITY_CLASSIFIED,
            'Covert' => VirtualItem::RARITY_COVERT,
            'Contraband' => VirtualItem::RARITY_CONTRABAND,
            // Дополнительные из JSON
            'Extraordinary' => VirtualItem::RARITY_COVERT,
            'Exotic' => VirtualItem::RARITY_CLASSIFIED,
            'Remarkable' => VirtualItem::RARITY_RESTRICTED,
            'High Grade' => VirtualItem::RARITY_MIL_SPEC,
            'Base Grade' => VirtualItem::RARITY_CONSUMER,
        ];

        return $map[$rarity] ?? $rarity;
    }
}
