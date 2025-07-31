<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\ExtensionEvents;

class TestTradeStatus extends Command
{
    protected $signature = 'test:trade-status {sellerId=3} {tradeOfferId=8312074884231}';
    protected $description = 'Test trade offer status checking';

    public function handle()
    {
        $sellerId = (int) $this->argument('sellerId');
        $tradeOfferId = $this->argument('tradeOfferId');

        $this->info('=== Тест проверки статуса трейда ===');
        $this->info("Seller ID: {$sellerId}");
        $this->info("Trade Offer ID: {$tradeOfferId}");
        $this->info('');

        try {
            // Отправляем команду на проверку статуса трейда
            ExtensionEvents::sendSmart('check_trade_status', $sellerId, [
                'trade_offer_ids' => [$tradeOfferId]
            ], '');
            
            $this->info('✅ Команда успешно отправлена расширению!');
            $this->info('Ожидайте ответ в логах сервера...');
            $this->info('');
            
            $this->info('Ожидаемые ответы от расширения:');
            $this->info('- trade_status_results - успешный результат');
            $this->info('- trade_status_error - ошибка проверки');
            $this->info('');
            
            $this->info('Проверьте логи Laravel для результата:');
            $this->info('tail -f storage/logs/laravel.log | grep trade_status');
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка отправки команды: ' . $e->getMessage());
        }
    }
}