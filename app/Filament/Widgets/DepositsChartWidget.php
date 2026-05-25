<?php

namespace App\Filament\Widgets;

use App\Exports\DepositsExport;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class DepositsChartWidget extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?string $filter = 'week';

    protected string $view = 'filament.widgets.chart-with-export';

    public function getHeading(): string
    {
        return 'Пополнения';
    }

    public function getExportName(): string
    {
        return 'deposits';
    }

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Экспорт XLSX')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                DatePicker::make('date_from')
                    ->label('Дата от')
                    ->default(now()->subWeek())
                    ->required(),
                DatePicker::make('date_to')
                    ->label('Дата до')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (array $data) {
                $from = Carbon::parse($data['date_from'])->format('Y-m-d');
                $to = Carbon::parse($data['date_to'])->format('Y-m-d');
                return Excel::download(
                    new DepositsExport($data['date_from'], $data['date_to']),
                    "deposits_{$from}_{$to}.xlsx"
                );
            });
    }

    protected function getFilters(): ?array
    {
        return [
            'week' => 'Неделя',
            'month' => 'Месяц',
            'year' => 'Год',
        ];
    }

    protected function getData(): array
    {
        $data = match ($this->filter) {
            'week' => $this->getWeekData(),
            'month' => $this->getMonthData(),
            'year' => $this->getYearData(),
            default => $this->getWeekData(),
        };

        return [
            'datasets' => [
                [
                    'label' => 'Пополнения (₽)',
                    'data' => $data['values'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getWeekData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d.m');

            $values[] = (float) Payment::where('status', Payment::STATUS_PAID)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->whereDate('paid_at', $date)
                ->sum('amount');
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getMonthData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d.m');

            $values[] = (float) Payment::where('status', Payment::STATUS_PAID)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->whereDate('paid_at', $date)
                ->sum('amount');
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getYearData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->translatedFormat('M');

            $values[] = (float) Payment::where('status', Payment::STATUS_PAID)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->sum('amount');
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
