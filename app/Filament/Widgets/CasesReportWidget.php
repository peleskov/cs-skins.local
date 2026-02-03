<?php

namespace App\Filament\Widgets;

use App\Exports\CasesReportExport;
use App\Models\CaseModel;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Maatwebsite\Excel\Facades\Excel;

class CasesReportWidget extends BaseWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $heading = 'Популярность кейсов';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.table-with-export';

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Экспорт XLSX')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn () => Excel::download(
                new CasesReportExport(),
                'cases_report_' . now()->format('Y-m-d') . '.xlsx'
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Кейс')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opens_count')
                    ->label('Открытий')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('opens_sum_price_paid')
                    ->label('Выручка')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('total_won')
                    ->label('Выплачено')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('avg_check')
                    ->label('Средний чек')
                    ->getStateUsing(function ($record) {
                        if ($record->opens_count > 0) {
                            return number_format($record->opens_sum_price_paid / $record->opens_count, 2, ',', ' ') . ' ₽';
                        }
                        return '0 ₽';
                    }),
                Tables\Columns\TextColumn::make('payout_ratio')
                    ->label('Коэф. выплат')
                    ->getStateUsing(function ($record) {
                        if ($record->opens_sum_price_paid > 0) {
                            $ratio = ($record->total_won / $record->opens_sum_price_paid) * 100;
                            return number_format($ratio, 1) . '%';
                        }
                        return '0%';
                    })
                    ->color(fn ($record) => $record->total_won > $record->opens_sum_price_paid ? 'danger' : 'success'),
            ])
            ->defaultSort('opens_count', 'desc')
            ->paginated([5, 10, 25]);
    }

    protected function getQuery()
    {
        return CaseModel::query()
            ->select('cases.*')
            ->selectRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.case_id = cases.id) as opens_count')
            ->selectRaw('(SELECT COALESCE(SUM(price_paid), 0) FROM case_opens WHERE case_opens.case_id = cases.id) as opens_sum_price_paid')
            ->selectRaw('(SELECT COALESCE(SUM(cii.price), 0) FROM case_inventory_items cii
                INNER JOIN case_opens co ON co.id = cii.source_id
                WHERE cii.source_type = \'case_open\' AND co.case_id = cases.id) as total_won')
            ->orderByDesc('opens_count');
    }
}
