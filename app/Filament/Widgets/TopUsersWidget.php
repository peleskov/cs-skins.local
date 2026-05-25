<?php

namespace App\Filament\Widgets;

use App\Exports\TopUsersExport;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Maatwebsite\Excel\Facades\Excel;

class TopUsersWidget extends BaseWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $heading = 'Топ пользователей';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.table-with-export';

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Экспорт XLSX')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn () => Excel::download(
                new TopUsersExport(),
                'top_users_' . now()->format('Y-m-d') . '.xlsx'
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Пользователь')
                    ->searchable(),
                Tables\Columns\TextColumn::make('case_opens_count')
                    ->label('Открытий')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('total_won')
                    ->label('Выиграно')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('luck')
                    ->label('Удача')
                    ->getStateUsing(function ($record) {
                        if ($record->total_spent > 0) {
                            $luck = ($record->total_won / $record->total_spent) * 100;
                            return number_format($luck, 1) . '%';
                        }
                        return '0%';
                    })
                    ->color(fn ($record) => $record->total_won > $record->total_spent ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('upgrades_count')
                    ->label('Апгрейдов')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('upgrades_wins')
                    ->label('Побед')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('upgrade_winrate')
                    ->label('Winrate')
                    ->getStateUsing(function ($record) {
                        if ($record->upgrades_count > 0) {
                            $rate = ($record->upgrades_wins / $record->upgrades_count) * 100;
                            return number_format($rate, 1) . '%';
                        }
                        return '0%';
                    }),
            ])
            ->defaultSort('case_opens_count', 'desc')
            ->paginated([5, 10, 25]);
    }

    protected function getQuery()
    {
        return Client::query()
            ->notRigged()
            ->select('clients.*')
            ->selectRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.client_id = clients.id) as case_opens_count')
            ->selectRaw('(SELECT COALESCE(SUM(price_paid), 0) FROM case_opens WHERE case_opens.client_id = clients.id) as total_spent')
            ->selectRaw('(SELECT COALESCE(SUM(cii.price), 0) FROM case_inventory_items cii WHERE cii.client_id = clients.id AND cii.source_type = \'case_open\') as total_won')
            ->selectRaw('(SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id) as upgrades_count')
            ->selectRaw('(SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id AND upgrades.result = \'win\') as upgrades_wins')
            ->havingRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.client_id = clients.id) > 0 OR (SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id) > 0');
    }
}
