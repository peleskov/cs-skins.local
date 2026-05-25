<?php

namespace App\Filament\Widgets;

use App\Models\BonusTransaction;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Upgrade;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialStatsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $weekAgo = Carbon::now()->subWeek();
        $notRigged = fn ($q) => $q->whereHas('client', fn ($c) => $c->notRigged());

        // Пополнения за неделю
        $deposits = Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum('amount');

        // Расходы на кейсы (основной баланс)
        $caseExpensesMain = Transaction::where('type', 'case_purchase')
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum(DB::raw('ABS(amount)'));

        // Расходы на кейсы (бонусный баланс)
        $caseExpensesBonus = BonusTransaction::where('description', 'like', '%кейс%')
            ->where('amount', '<', 0)
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum(DB::raw('ABS(amount)'));

        // Выводы
        $withdrawals = Transaction::where('type', 'withdraw')
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum(DB::raw('ABS(amount)'));

        // Бонусы выданные
        $bonusIssued = BonusTransaction::where('amount', '>', 0)
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum('amount');

        // Апгрейды
        $upgradesBets = Upgrade::where('created_at', '>=', $weekAgo)->tap($notRigged)->sum('total_bet');
        $upgradesPrizes = Upgrade::where('result', 'win')
            ->where('created_at', '>=', $weekAgo)
            ->tap($notRigged)
            ->sum('target_price');
        $upgradesProfit = $upgradesBets - $upgradesPrizes;

        // Общая прибыль
        $totalProfit = $deposits - $withdrawals - $bonusIssued + $upgradesProfit;

        return [
            Stat::make('Пополнения', '+' . number_format($deposits, 0, ',', ' ') . ' ₽')
                ->description('За последнюю неделю')
                ->descriptionIcon('heroicon-o-arrow-down-tray')
                ->color('success'),

            Stat::make('Кейсы (осн.)', number_format($caseExpensesMain, 0, ',', ' ') . ' ₽')
                ->description('С основного баланса')
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary'),

            Stat::make('Кейсы (бонус)', number_format($caseExpensesBonus, 0, ',', ' ') . ' ₽')
                ->description('С бонусного баланса')
                ->descriptionIcon('heroicon-o-gift')
                ->color('warning'),

            Stat::make('Выводы', '-' . number_format($withdrawals, 0, ',', ' ') . ' ₽')
                ->description('За последнюю неделю')
                ->descriptionIcon('heroicon-o-arrow-up-tray')
                ->color('danger'),

            Stat::make('Бонусы выданы', number_format($bonusIssued, 0, ',', ' ') . ' ₽')
                ->description('Промокоды и акции')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('info'),

            Stat::make('Прибыль апгрейдов', ($upgradesProfit >= 0 ? '+' : '') . number_format($upgradesProfit, 0, ',', ' ') . ' ₽')
                ->description("Ставки: " . number_format($upgradesBets, 0, ',', ' ') . " ₽")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($upgradesProfit >= 0 ? 'success' : 'danger'),
        ];
    }
}
