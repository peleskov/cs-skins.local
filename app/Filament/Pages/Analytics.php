<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class Analytics extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Аналитика';

    protected static ?string $title = 'Аналитика и отчёты';

    protected static string|\UnitEnum|null $navigationGroup = 'Отчёты';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.analytics';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\AnalyticsStatsWidget::class,
            \App\Filament\Widgets\DepositsChartWidget::class,
            \App\Filament\Widgets\CaseOpensChartWidget::class,
            \App\Filament\Widgets\UpgradesChartWidget::class,
            \App\Filament\Widgets\FinancialStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\CasesReportWidget::class,
            \App\Filament\Widgets\PromocodesReportWidget::class,
            \App\Filament\Widgets\TopUsersWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
