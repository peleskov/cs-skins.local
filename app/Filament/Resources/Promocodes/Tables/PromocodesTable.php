<?php

namespace App\Filament\Resources\Promocodes\Tables;

use App\Models\Promocode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromocodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Promocode::TYPE_PERCENT => 'Процент',
                        Promocode::TYPE_FIXED => 'Фикс. сумма',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Promocode::TYPE_PERCENT => 'info',
                        Promocode::TYPE_FIXED => 'success',
                    }),

                TextColumn::make('value')
                    ->label('Значение')
                    ->formatStateUsing(fn ($state, $record): string =>
                        $record->type === Promocode::TYPE_PERCENT
                            ? "{$state}%"
                            : number_format($state, 0, '.', ' ') . ' ₽'
                    )
                    ->sortable(),

                TextColumn::make('min_deposit')
                    ->label('Мин. депозит')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('usage')
                    ->label('Использовано')
                    ->state(fn ($record): string =>
                        $record->max_uses
                            ? "{$record->used_count} / {$record->max_uses}"
                            : "{$record->used_count} / ∞"
                    ),

                TextColumn::make('expires_at')
                    ->label('Истекает')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Бессрочный'),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        Promocode::TYPE_PERCENT => 'Процент',
                        Promocode::TYPE_FIXED => 'Фикс. сумма',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Редактировать'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
