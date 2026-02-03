<?php

namespace App\Filament\Resources\BonusTransactions\Tables;

use App\Models\BonusTransaction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BonusTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BonusTransaction::TYPE_CREDIT => 'Начисление',
                        BonusTransaction::TYPE_DEBIT => 'Списание',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        BonusTransaction::TYPE_CREDIT => 'success',
                        BonusTransaction::TYPE_DEBIT => 'danger',
                    }),

                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('promocode.code')
                    ->label('Промокод')
                    ->placeholder('—'),

                TextColumn::make('case.name')
                    ->label('Кейс')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        BonusTransaction::TYPE_CREDIT => 'Начисление',
                        BonusTransaction::TYPE_DEBIT => 'Списание',
                    ]),

                SelectFilter::make('client_id')
                    ->label('Клиент')
                    ->relationship('client', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('')
                    ->tooltip('Просмотр'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
