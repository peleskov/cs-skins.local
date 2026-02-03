<?php

namespace App\Filament\Resources\VirtualItems\Tables;

use App\Models\VirtualItem;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class VirtualItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->width(50)
                    ->height(40),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(['name', 'market_hash_name', 'skin_name'])
                    ->sortable()
                    ->limit(35)
                    ->tooltip(fn (VirtualItem $record): string => $record->market_hash_name),

                TextColumn::make('weapon_type')
                    ->label('Тип')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quality')
                    ->label('Качество')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Factory New' => 'success',
                        'Minimal Wear' => 'info',
                        'Field-Tested' => 'warning',
                        'Well-Worn' => 'danger',
                        'Battle-Scarred' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('rarity')
                    ->label('Редкость')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Consumer Grade' => 'gray',
                        'Industrial Grade' => 'info',
                        'Mil-Spec Grade' => 'primary',
                        'Restricted' => 'purple',
                        'Classified' => 'pink',
                        'Covert' => 'danger',
                        'Contraband' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('steam_price')
                    ->label('Цена Steam')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->placeholder('—'),

                IconColumn::make('is_stattrak')
                    ->label('ST')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon(''),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('weapon_type')
                    ->label('Тип оружия')
                    ->options(fn () => VirtualItem::query()
                        ->distinct()
                        ->whereNotNull('weapon_type')
                        ->pluck('weapon_type', 'weapon_type')
                        ->toArray()
                    )
                    ->searchable(),

                SelectFilter::make('quality')
                    ->label('Качество')
                    ->options(array_combine(
                        VirtualItem::getQualities(),
                        VirtualItem::getQualities()
                    )),

                SelectFilter::make('rarity')
                    ->label('Редкость')
                    ->options(array_combine(
                        VirtualItem::getRarities(),
                        VirtualItem::getRarities()
                    )),

                Filter::make('price_range')
                    ->form([
                        TextInput::make('price_from')
                            ->label('Цена от')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('price_to')
                            ->label('Цена до')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'] ?? null,
                                fn (Builder $query, $price): Builder => $query->where('steam_price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'] ?? null,
                                fn (Builder $query, $price): Builder => $query->where('steam_price', '<=', $price),
                            );
                    }),

                TernaryFilter::make('is_stattrak')
                    ->label('StatTrak'),

                TernaryFilter::make('is_souvenir')
                    ->label('Сувенир'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('steam_price', 'desc');
    }
}
