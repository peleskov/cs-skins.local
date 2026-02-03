<?php

namespace App\Filament\Resources\Upgrades\Widgets;

use App\Models\Upgrade;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UpgradeStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = Upgrade::count();
        $wins = Upgrade::where('result', 'win')->count();
        $loses = Upgrade::where('result', 'lose')->count();

        $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;

        $totalBets = Upgrade::sum('total_bet');
        $totalPrizes = Upgrade::where('result', 'win')->sum('target_price');
        $profit = $totalBets - $totalPrizes;

        // За последние 24 часа
        $today = now()->subDay();
        $todayTotal = Upgrade::where('created_at', '>=', $today)->count();
        $todayWins = Upgrade::where('created_at', '>=', $today)->where('result', 'win')->count();
        $todayBets = Upgrade::where('created_at', '>=', $today)->sum('total_bet');

        return [
            Stat::make('Всего апгрейдов', number_format($total))
                ->description("За 24ч: {$todayTotal}")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary'),

            Stat::make('Выигрыши / Проигрыши', "{$wins} / {$loses}")
                ->description("Win rate: {$winRate}%")
                ->descriptionIcon($winRate > 50 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($winRate > 50 ? 'danger' : 'success'),

            Stat::make('Оборот ставок', number_format($totalBets, 0, ',', ' ') . ' ₽')
                ->description("За 24ч: " . number_format($todayBets, 0, ',', ' ') . ' ₽')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Прибыль сайта', number_format($profit, 0, ',', ' ') . ' ₽')
                ->description($profit >= 0 ? 'В плюсе' : 'В минусе')
                ->descriptionIcon($profit >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($profit >= 0 ? 'success' : 'danger'),
        ];
    }
}
