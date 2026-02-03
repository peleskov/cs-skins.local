<?php

namespace App\Filament\Widgets;

use App\Exports\PromocodesReportExport;
use App\Models\Promocode;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Maatwebsite\Excel\Facades\Excel;

class PromocodesReportWidget extends BaseWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $heading = 'Эффективность промокодов';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.table-with-export';

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Экспорт XLSX')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn () => Excel::download(
                new PromocodesReportExport(),
                'promocodes_report_' . now()->format('Y-m-d') . '.xlsx'
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Promocode::query()
                    ->withCount('bonusTransactions')
                    ->withSum('bonusTransactions', 'amount')
                    ->orderByDesc('bonus_transactions_count')
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'percent' ? 'Процент' : 'Фиксированный'),
                Tables\Columns\TextColumn::make('value')
                    ->label('Значение')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->type === 'percent' ? $state . '%' : $state . ' ₽';
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('bonus_transactions_count')
                    ->label('Использований')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('bonus_transactions_sum_amount')
                    ->label('Выдано бонусов')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('max_uses')
                    ->label('Лимит')
                    ->default('∞'),
                Tables\Columns\TextColumn::make('conversion')
                    ->label('Конверсия')
                    ->getStateUsing(function ($record) {
                        if ($record->max_uses && $record->max_uses > 0) {
                            $rate = ($record->bonus_transactions_count / $record->max_uses) * 100;
                            return number_format($rate, 1) . '%';
                        }
                        return '—';
                    }),
            ])
            ->defaultSort('bonus_transactions_count', 'desc')
            ->paginated([5, 10, 25]);
    }
}
