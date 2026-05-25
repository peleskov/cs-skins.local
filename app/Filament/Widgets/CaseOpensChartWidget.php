<?php

namespace App\Filament\Widgets;

use App\Exports\CaseOpensExport;
use App\Models\CaseOpen;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CaseOpensChartWidget extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?string $filter = 'week';

    protected string $view = 'filament.widgets.chart-with-export';

    public function getHeading(): string
    {
        return 'Открытия кейсов';
    }

    public function getExportName(): string
    {
        return 'case_opens';
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
                    new CaseOpensExport($data['date_from'], $data['date_to']),
                    "case_opens_{$from}_{$to}.xlsx"
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
                    'label' => 'Открытий',
                    'data' => $data['values'],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
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

            $values[] = CaseOpen::whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
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

            $values[] = CaseOpen::whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
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

            $values[] = CaseOpen::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
