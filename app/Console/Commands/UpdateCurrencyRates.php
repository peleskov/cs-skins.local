<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Exception;

class UpdateCurrencyRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update {--force : Принудительное обновление даже если были ошибки}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновить курсы валют из внешнего API';

    /**
     * Сервис для работы с валютами
     */
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        parent::__construct();
        $this->currencyService = $currencyService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Начинаем обновление курсов валют...');
        
        try {
            // Показываем текущую статистику
            $stats = $this->currencyService->getCurrencyStats();
            $this->info("📊 Основная валюта: {$stats['primary_currency']}");
            $this->info("📊 Активных валют: {$stats['active_currencies']} из {$stats['total_currencies']}");
            
            if ($stats['last_update']) {
                $this->info("📅 Последнее обновление: {$stats['last_update']}");
            }

            $this->newLine();

            // Обновляем курсы
            $results = $this->currencyService->updateExchangeRates();

            if (empty($results)) {
                $this->warn('⚠️  Нет валют для обновления');
                return self::SUCCESS;
            }

            // Выводим результаты
            $this->displayResults($results);

            $updated = count(array_filter($results, fn($r) => $r['status'] === 'updated'));
            $notFound = count(array_filter($results, fn($r) => $r['status'] === 'not_found'));

            $this->newLine();
            $this->info("✅ Обновление завершено!");
            $this->info("📈 Обновлено: {$updated} валют");
            
            if ($notFound > 0) {
                $this->warn("⚠️  Не найдено: {$notFound} валют");
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Ошибка при обновлении курсов валют:');
            $this->error($e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Показать результаты обновления в виде таблицы
     */
    protected function displayResults(array $results): void
    {
        $tableData = [];
        
        foreach ($results as $result) {
            $status = match($result['status']) {
                'updated' => '✅ Обновлён',
                'not_found' => '❌ Не найден',
                default => '⚠️  Неизвестно'
            };

            $change = '';
            if ($result['status'] === 'updated' && $result['old_rate'] && $result['new_rate']) {
                $diff = $result['new_rate'] - $result['old_rate'];
                $percent = ($diff / $result['old_rate']) * 100;
                
                if ($diff > 0) {
                    $change = sprintf('<fg=green>+%.4f (+%.2f%%)</>', $diff, $percent);
                } elseif ($diff < 0) {
                    $change = sprintf('<fg=red>%.4f (%.2f%%)</>', $diff, $percent);
                } else {
                    $change = '<fg=gray>Без изменений</>';
                }
            }

            $tableData[] = [
                $result['currency'],
                number_format($result['old_rate'] ?? 0, 4),
                number_format($result['new_rate'] ?? 0, 4),
                $change,
                $status
            ];
        }

        $this->table(
            ['Валюта', 'Старый курс', 'Новый курс', 'Изменение', 'Статус'],
            $tableData
        );
    }
}