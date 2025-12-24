<?php

namespace App\Console\Commands;

use App\Models\TradeOffer;
use App\Services\Steam\TradeService;
use App\Services\Steam\SessionCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckTradeStatus extends Command
{
    protected $signature = 'trade:check {trade_id? : ID трейда в нашей БД} {--steam-id= : Steam Trade Offer ID напрямую}';

    protected $description = 'Проверить статус трейда через Steam API и получить escrow информацию';

    public function handle(SessionCache $sessionCache)
    {
        $tradeId = $this->argument('trade_id');
        $steamTradeOfferId = $this->option('steam-id');

        if (!$tradeId && !$steamTradeOfferId) {
            // Берём последний трейд
            $tradeOffer = TradeOffer::latest()->first();
            if (!$tradeOffer) {
                $this->error('Нет трейдов в базе');
                return Command::FAILURE;
            }
            $this->info("Используем последний трейд #{$tradeOffer->id}");
        } elseif ($tradeId) {
            $tradeOffer = TradeOffer::find($tradeId);
            if (!$tradeOffer) {
                $this->error("Трейд #{$tradeId} не найден");
                return Command::FAILURE;
            }
        } else {
            // Ищем по steam_trade_offer_id
            $tradeOffer = TradeOffer::where('steam_trade_offer_id', $steamTradeOfferId)->first();
            if (!$tradeOffer) {
                $this->warn("Трейд со Steam ID {$steamTradeOfferId} не найден в БД, пробуем запросить напрямую");
            }
        }

        // Показываем инфо из нашей БД
        if ($tradeOffer) {
            $this->info('');
            $this->info('=== Данные из нашей БД ===');
            $this->table(
                ['Поле', 'Значение'],
                [
                    ['ID', $tradeOffer->id],
                    ['Steam Trade Offer ID', $tradeOffer->steam_trade_offer_id],
                    ['Seller ID', $tradeOffer->seller_id],
                    ['Buyer ID', $tradeOffer->buyer_id],
                    ['Status (DB)', $tradeOffer->status],
                    ['Order ID', $tradeOffer->order_id],
                    ['Created', $tradeOffer->created_at],
                ]
            );

            $steamTradeOfferId = $tradeOffer->steam_trade_offer_id;
            $sellerId = $tradeOffer->seller_id;
        }

        if (!$steamTradeOfferId) {
            $this->error('Steam Trade Offer ID не найден');
            return Command::FAILURE;
        }

        // Получаем сессию продавца
        $sessionData = $sessionCache->get($sellerId ?? 1);
        if (!$sessionData) {
            $this->error('Нет кешированной Steam сессии для продавца. Убедитесь что расширение активно.');
            return Command::FAILURE;
        }

        // Извлекаем access_token
        $accessToken = $this->extractAccessToken($sessionData);
        if (!$accessToken) {
            $this->error('Не удалось извлечь access_token из сессии');
            return Command::FAILURE;
        }

        $this->info('');
        $this->info('=== Запрос к Steam API ===');

        try {
            $response = Http::timeout(15)->get('https://api.steampowered.com/IEconService/GetTradeOffer/v1/', [
                'access_token' => $accessToken,
                'tradeofferid' => $steamTradeOfferId,
                'language' => 'english'
            ]);

            if (!$response->successful()) {
                $this->error("HTTP ошибка: {$response->status()}");
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['response']['offer'])) {
                $this->error('Трейд не найден в Steam');
                $this->line('Response: ' . json_encode($data, JSON_PRETTY_PRINT));
                return Command::FAILURE;
            }

            $offer = $data['response']['offer'];

            $this->info('');
            $this->info('=== Данные от Steam API ===');

            $statusMap = [
                1 => 'Invalid',
                2 => 'Active',
                3 => 'Accepted',
                4 => 'Countered',
                5 => 'Expired',
                6 => 'Canceled',
                7 => 'Declined',
                8 => 'InvalidItems',
                9 => 'CreatedNeedsConfirmation',
                10 => 'CanceledBySecondFactor',
                11 => 'InEscrow',
            ];

            $stateCode = $offer['trade_offer_state'] ?? 0;
            $stateText = $statusMap[$stateCode] ?? 'Unknown';

            $tableData = [
                ['trade_offer_state', $stateCode . ' (' . $stateText . ')'],
                ['time_created', isset($offer['time_created']) ? date('Y-m-d H:i:s', $offer['time_created']) : 'N/A'],
                ['time_updated', isset($offer['time_updated']) ? date('Y-m-d H:i:s', $offer['time_updated']) : 'N/A'],
                ['escrow_end_date', isset($offer['escrow_end_date']) && $offer['escrow_end_date'] ? date('Y-m-d H:i:s', $offer['escrow_end_date']) : '0 (нет escrow)'],
                ['delay_settlement', ($offer['delay_settlement'] ?? false) ? 'TRUE ✓' : 'false'],
                ['settlement_date', isset($offer['settlement_date']) && $offer['settlement_date'] ? date('Y-m-d H:i:s', $offer['settlement_date']) . ' (timestamp: ' . $offer['settlement_date'] . ')' : 'НЕТ'],
                ['expiration_time', isset($offer['expiration_time']) ? date('Y-m-d H:i:s', $offer['expiration_time']) : 'N/A'],
                ['confirmation_method', $offer['confirmation_method'] ?? 'N/A'],
                ['tradeid', $offer['tradeid'] ?? 'N/A'],
            ];

            $this->table(['Поле', 'Значение'], $tableData);

            // Показываем сырые данные для отладки
            if ($this->output->isVerbose()) {
                $this->info('');
                $this->info('=== Raw Steam Response ===');
                $this->line(json_encode($offer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Выводим важную информацию
            $this->info('');

            $hasSettlementHold = ($offer['delay_settlement'] ?? false) && !empty($offer['settlement_date']);

            if ($stateCode === 11) {
                $this->warn('⏳ Трейд в ESCROW (холд Steam до принятия)');
                if (isset($offer['escrow_end_date']) && $offer['escrow_end_date']) {
                    $endDate = date('Y-m-d H:i:s', $offer['escrow_end_date']);
                    $remaining = $offer['escrow_end_date'] - time();
                    $days = floor($remaining / 86400);
                    $hours = floor(($remaining % 86400) / 3600);
                    $this->warn("   Escrow закончится: {$endDate} (через {$days}д {$hours}ч)");
                }
            } elseif ($stateCode === 3) {
                if ($hasSettlementHold) {
                    $this->warn('⏳ Трейд ПРИНЯТ, но в ХОЛДЕ (delay_settlement)');
                    $endDate = date('Y-m-d H:i:s', $offer['settlement_date']);
                    $remaining = $offer['settlement_date'] - time();
                    $days = floor($remaining / 86400);
                    $hours = floor(($remaining % 86400) / 3600);
                    $this->warn("   Холд закончится: {$endDate} (через {$days}д {$hours}ч)");
                    $this->warn("   НЕ ВЫПЛАЧИВАТЬ продавцу до окончания холда!");
                } else {
                    $this->info('✅ Трейд ЗАВЕРШЁН (Accepted) - без холда');
                }
            } elseif ($stateCode === 2) {
                $this->info('⏳ Трейд АКТИВЕН, ожидает принятия');
            } else {
                $this->warn("⚠️ Статус: {$stateText}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Ошибка запроса к Steam API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function extractAccessToken(array $sessionData): ?string
    {
        if (!isset($sessionData['steamLoginSecure'])) {
            return null;
        }

        $cookieValue = urldecode($sessionData['steamLoginSecure']);
        $parts = explode('||', $cookieValue);

        return $parts[1] ?? null;
    }
}
