<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\BonusTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromocodeActivationsRelationManager extends RelationManager
{
    protected static string $relationship = 'bonusTransactions';

    protected static ?string $title = 'Активированные промокоды';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('type', BonusTransaction::TYPE_CREDIT)
                ->whereNotNull('promocode_id')
                ->with(['promocode', 'payment'])
            )
            ->columns([
                TextColumn::make('promocode.code')
                    ->label('Промокод')
                    ->url(fn ($record) => $record->promocode_id
                        ? route('filament.admin.resources.promocodes.edit', ['record' => $record->promocode_id])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('payment.amount')
                    ->label('Сумма пополнения')
                    ->money('RUB')
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label('Бонус')
                    ->money('RUB'),

                TextColumn::make('total')
                    ->label('Итоговая сумма')
                    ->state(fn ($record) => (float) ($record->payment?->amount ?? 0) + (float) $record->amount)
                    ->money('RUB'),

                TextColumn::make('created_at')
                    ->label('Дата активации')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
