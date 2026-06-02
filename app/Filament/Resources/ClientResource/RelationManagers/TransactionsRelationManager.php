<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Транзакции';

    /** Кэш накопительного баланса по id транзакции */
    private ?array $runningTotals = null;

    /** Типы, увеличивающие баланс (остальные — списание) */
    private const CREDIT_TYPES = ['deposit', 'sale', 'refund', 'auction_refund', 'virtual_item_sale', 'promocode'];

    private function runningTotalFor($record): float
    {
        if ($this->runningTotals === null) {
            $this->runningTotals = [];
            $sum = 0.0;

            $rows = $this->getOwnerRecord()->transactions()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'type', 'amount']);

            foreach ($rows as $row) {
                $sum += in_array($row->type, self::CREDIT_TYPES, true)
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
                        'deposit' => 'Пополнение',
                        'withdrawal' => 'Вывод',
                        'purchase' => 'Покупка',
                        'sale' => 'Продажа',
                        'fee' => 'Комиссия',
                        'refund' => 'Возврат',
                        'auction_bid' => 'Ставка',
                        'auction_refund' => 'Возврат ставки',
                        'case_purchase' => 'Открытие кейса',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'deposit', 'sale', 'refund', 'auction_refund' => 'success',
                        'withdrawal', 'purchase', 'fee', 'auction_bid', 'case_purchase' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ожидает',
                        'completed' => 'Завершена',
                        'failed' => 'Ошибка',
                        'cancelled' => 'Отменена',
                        'on_hold' => 'Заморожена',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending', 'on_hold' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50),

                TextColumn::make('running_total')
                    ->label('Итого')
                    ->state(fn ($record): float => $this->runningTotalFor($record))
                    ->money('RUB'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'deposit' => 'Пополнение',
                        'withdrawal' => 'Вывод',
                        'purchase' => 'Покупка',
                        'sale' => 'Продажа',
                        'fee' => 'Комиссия',
                        'refund' => 'Возврат',
                        'case_purchase' => 'Открытие кейса',
                    ]),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'completed' => 'Завершена',
                        'failed' => 'Ошибка',
                        'cancelled' => 'Отменена',
                        'on_hold' => 'Заморожена',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
