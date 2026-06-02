<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BonusTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'bonusTransactions';

    protected static ?string $title = 'Бонусные транзакции';

    /** Кэш накопительного бонусного баланса по id транзакции */
    private ?array $runningTotals = null;

    private function runningTotalFor($record): float
    {
        if ($this->runningTotals === null) {
            $this->runningTotals = [];
            $sum = 0.0;

            $rows = $this->getOwnerRecord()->bonusTransactions()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'type', 'amount']);

            foreach ($rows as $row) {
                $sum += $row->type === 'credit'
                    ? (float) $row->amount
                    : -(float) $row->amount;

                $this->runningTotals[$row->id] = $sum;
            }
        }

        return $this->runningTotals[$record->id] ?? 0.0;
    }

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

                TextColumn::make('running_total')
                    ->label('Итого')
                    ->state(fn ($record): float => $this->runningTotalFor($record))
                    ->money('RUB'),
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
