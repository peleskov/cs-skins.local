<?php

namespace App\Filament\Widgets;

use App\Exports\UpgradesExport;
use App\Models\Upgrade;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UpgradesChartWidget extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?string $filter = 'week';

    protected string $view = 'filament.widgets.chart-with-export';

    public function getHeading(): string
    {
        return 'Апгрейды (выигрыши/проигрыши)';
    }

    public function getExportName(): string
    {
        return 'upgrades';
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
                    new UpgradesExport($data['date_from'], $data['date_to']),
                    "upgrades_{$from}_{$to}.xlsx"
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
                    'label' => 'Выигрыши',
                    'data' => $data['wins'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                ],
                [
                    'label' => 'Проигрыши',
                    'data' => $data['loses'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getWeekData(): array
    {
        $labels = [];
        $wins = [];
        $loses = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d.m');

            $wins[] = Upgrade::where('result', 'win')
                ->whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();

            $loses[] = Upgrade::where('result', 'lose')
                ->whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
        }

        return ['labels' => $labels, 'wins' => $wins, 'loses' => $loses];
    }

    protected function getMonthData(): array
    {
        $labels = [];
        $wins = [];
        $loses = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d.m');

            $wins[] = Upgrade::where('result', 'win')
                ->whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();

            $loses[] = Upgrade::where('result', 'lose')
                ->whereDate('created_at', $date)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
        }

        return ['labels' => $labels, 'wins' => $wins, 'loses' => $loses];
    }

    protected function getYearData(): array
    {
        $labels = [];
        $wins = [];
        $loses = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->translatedFormat('M');

            $wins[] = Upgrade::where('result', 'win')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();

            $loses[] = Upgrade::where('result', 'lose')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->whereHas('client', fn ($c) => $c->notRigged())
                ->count();
        }

        return ['labels' => $labels, 'wins' => $wins, 'loses' => $loses];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
