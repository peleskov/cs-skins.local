<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BotRotationService
{
    /**
     * Получить следующего доступного бота для покупки
     * 
     * @param float $requiredAmount Сумма необходимая для покупки
     * @return Client|null
     */
    public function getNextAvailableBot(float $requiredAmount): ?Client
    {
        $dailyLimit = config('bot.daily_order_limit', 200);
        
        // Получаем всех ботов с положительным балансом
        $bots = Client::where('is_bot', true)
            ->where('balance', '>', 0)
            ->whereNotNull('steam_trade_url')
            ->get();
        
        if ($bots->isEmpty()) {
            return null;
        }
        
        // Фильтруем ботов по дневному лимиту
        $availableBots = $bots->filter(function ($bot) use ($dailyLimit) {
            $todayOrdersCount = $this->getTodayOrdersCount($bot->id);
            return $todayOrdersCount < $dailyLimit;
        });
        
        if ($availableBots->isEmpty()) {
            return null;
        }
        
        // Сортируем по времени последней сделки (сначала те, кто давно не использовался)
        return $availableBots->sortBy(function ($bot) {
            return $this->getLastOrderTime($bot->id);
        })->first();
    }
    
    /**
     * Получить количество заказов бота за сегодня
     * 
     * @param int $botId
     * @return int
     */
    protected function getTodayOrdersCount(int $botId): int
    {
        // Всегда получаем актуальные данные из БД
        return Order::where('buyer_id', $botId)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }
    
    /**
     * Получить время последнего заказа бота
     * 
     * @param int $botId
     * @return string|null
     */
    protected function getLastOrderTime(int $botId): ?string
    {
        // Всегда получаем актуальные данные из БД
        $lastOrder = Order::where('buyer_id', $botId)
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $lastOrder ? $lastOrder->created_at->toDateTimeString() : '2000-01-01 00:00:00';
    }
    
    /**
     * Проверить доступность бота для покупки
     * 
     * @param Client $bot
     * @param float $amount
     * @return array ['available' => bool, 'reason' => string|null]
     */
    public function checkBotAvailability(Client $bot, float $amount): array
    {
        // Проверка что это бот
        if (!$bot->is_bot) {
            return [
                'available' => false,
                'reason' => 'Client is not a bot'
            ];
        }
        
        // Проверка баланса (0 = выведен из ротации)
        if ($bot->balance <= 0) {
            return [
                'available' => false,
                'reason' => 'Bot is disabled (balance is 0)'
            ];
        }
        
        // Проверка trade URL
        if (empty($bot->steam_trade_url)) {
            return [
                'available' => false,
                'reason' => 'Bot has no trade URL'
            ];
        }
        
        // Проверка дневного лимита
        $dailyLimit = config('bot.daily_order_limit', 200);
        $todayOrdersCount = $this->getTodayOrdersCount($bot->id);
        
        if ($todayOrdersCount >= $dailyLimit) {
            return [
                'available' => false,
                'reason' => "Daily limit reached ({$todayOrdersCount}/{$dailyLimit})"
            ];
        }
        
        return [
            'available' => true,
            'reason' => null
        ];
    }
    
    /**
     * Очистить кэш для бота (вызывать после создания заказа)
     * 
     * @param int $botId
     */
    public function clearBotCache(int $botId): void
    {
        Cache::forget("bot_{$botId}_today_orders_count");
        Cache::forget("bot_{$botId}_last_order_time");
    }
    
    /**
     * Получить статистику по всем ботам
     * 
     * @return array
     */
    public function getBotsStatistics(): array
    {
        $bots = Client::where('is_bot', true)->get();
        $dailyLimit = config('bot.daily_order_limit', 200);
        
        return $bots->map(function ($bot) use ($dailyLimit) {
            $todayOrdersCount = $this->getTodayOrdersCount($bot->id);
            
            return [
                'id' => $bot->id,
                'name' => $bot->name,
                'balance' => $bot->balance,
                'is_active' => $bot->balance > 0,
                'today_orders' => $todayOrdersCount,
                'daily_limit' => $dailyLimit,
                'limit_remaining' => $dailyLimit - $todayOrdersCount,
                'has_trade_url' => !empty($bot->steam_trade_url),
                'last_order_at' => $this->getLastOrderTime($bot->id)
            ];
        })->toArray();
    }
}