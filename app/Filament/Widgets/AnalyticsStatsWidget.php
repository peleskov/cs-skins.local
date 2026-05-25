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

        $notRigged = fn ($q) => $q->whereHas('client', fn ($c) => $c->notRigged());

        // Пополнения
        $depositsToday = Payment::where('status', Payment::STATUS_PAID)
            ->whereDate('paid_at', $today)
            ->tap($notRigged)
            ->sum('amount');
        $depositsWeek = Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum('amount');

        // Кейсы
        $casesToday = CaseOpen::whereDate('created_at', $today)->tap($notRigged)->count();
        $casesWeek = CaseOpen::where('created_at', '>=', $weekAgo)->tap($notRigged)->count();

        // Апгрейды
        $upgradesToday = Upgrade::whereDate('created_at', $today)->tap($notRigged)->count();
        $upgradesWeek = Upgrade::where('created_at', '>=', $weekAgo)->tap($notRigged)->count();
        $upgradeWinsWeek = Upgrade::where('result', 'win')
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
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
