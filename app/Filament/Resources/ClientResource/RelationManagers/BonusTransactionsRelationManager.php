<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class BonusTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'bonusTransactions';

    protected static ?string $title = 'Бонусные транзакции';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'Начисление',
                        'debit' => 'Списание',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                    }),

                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50),

                TextColumn::make('promocode.code')
                    ->label('Промокод')
                    ->placeholder('-'),

                TextColumn::make('case.name')
                    ->label('Кейс')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'credit' => 'Начисление',
                        'debit' => 'Списание',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
