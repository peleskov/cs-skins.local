<?php

namespace App\Filament\Resources\Translations\Tables;

use App\Models\Translation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class TranslationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group')
                    ->label('Группа')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Ключ скопирован!')
                    ->weight('medium'),

                TextColumn::make('locale')
                    ->label('Язык')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('secondary')
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                TextColumn::make('value')
                    ->label('Значение')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->value;
                    })
                    ->formatStateUsing(function ($state) {
                        return empty($state) ? '—' : $state;
                    })
                    ->color(fn ($state) => empty($state) ? 'gray' : null),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Группа')
                    ->options(function () {
                        return Translation::query()
                            ->distinct()
                            ->pluck('group', 'group')
                            ->toArray();
                    }),

                SelectFilter::make('locale')
                    ->label('Язык')
                    ->options(function () {
                        return Translation::query()
                            ->distinct()
                            ->pluck('locale', 'locale')
                            ->mapWithKeys(fn ($locale) => [$locale => strtoupper($locale)])
                            ->toArray();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('group');
    }
}
