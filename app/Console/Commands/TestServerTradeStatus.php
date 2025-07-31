<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\ExtensionEvents;

class TestServerTradeStatus extends Command
{
    protected $signature = 'test:server-trade-status {sellerId=3} {tradeOfferId=8312074884231}';
    protected $description = 'Test getting cookies from extension and checking trade status on server';

    public function handle()
    {
        $sellerId = (int) $this->argument('sellerId');
        $tradeOfferId = $this->argument('tradeOfferId');

        $this->info('=== Тест получения куков и проверки трейда на сервере ===');
        $this->info("Seller ID: {$sellerId}");
        $this->info("Trade Offer ID: {$tradeOfferId}");
        $this->info('');

        try {
            // Отправляем команду на получение куков
            ExtensionEvents::sendSmart('get_cookies', $sellerId, [
                'trade_offer_id' => $tradeOfferId
            ], '');
            
            $this->info('✅ Команда на получение куков отправлена расширению!');
            $this->info('Ожидайте ответ в логах сервера...');
            $this->info('');
            
            $this->info('Ожидаемые ответы от расширения:');
            $this->info('- cookies_received - куки получены');
            $this->info('- cookies_error - ошибка получения куков');
            $this->info('');
            
            $this->info('Проверьте логи Laravel для результата:');
            $this->info('tail -f storage/logs/laravel.log | grep cookies');
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка отправки команды: ' . $e->getMessage());
        }
    }
}