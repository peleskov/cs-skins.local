<?php

namespace App\Filament\Widgets;

use App\Models\CaseOpen;
use App\Models\Payment;
use App\Models\Promocode;
use App\Models\Upgrade;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class AnalyticsStatsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Carbon::today();
        $weekAgo = Carbon::now()->subWeek();

        // Пополнения
        $depositsToday = Payment::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('amount');
        $depositsWeek = Payment::where('status', 'completed')
            ->where('created_at', '>=', $weekAgo)
            ->sum('amount');

        // Кейсы
        $casesToday = CaseOpen::whereDate('created_at', $today)->count();
        $casesWeek = CaseOpen::where('created_at', '>=', $weekAgo)->count();

        // Апгрейды
        $upgradesToday = Upgrade::whereDate('created_at', $today)->count();
        $upgradesWeek = Upgrade::where('created_at', '>=', $weekAgo)->count();
        $upgradeWinsWeek = Upgrade::where('result', 'win')
            ->where('created_at', '>=', $weekAgo)
            ->count();
        $winRate = $upgradesWeek > 0 ? round(($upgradeWinsWeek / $upgradesWeek) * 100, 1) : 0;

        // Активные промокоды
        $activePromos = Promocode::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        return [
            Stat::make('Пополнения сегодня', number_format($depositsToday, 0, ',', ' ') . ' ₽')
                ->description('За неделю: ' . number_format($depositsWeek, 0, ',', ' ') . ' ₽')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Открыто кейсов', $casesToday)
                ->description('Сегодня / За неделю: ' . $casesWeek)
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary'),

            Stat::make('Апгрейдов', $upgradesToday)
                ->description("За неделю: {$upgradesWeek} (Win rate: {$winRate}%)")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($winRate > 50 ? 'danger' : 'success'),

            Stat::make('Активных промокодов', $activePromos)
                ->description('Доступны для использования')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('warning'),
        ];
    }
}
