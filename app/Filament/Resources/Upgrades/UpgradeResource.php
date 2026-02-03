<?php

namespace App\Filament\Resources\Upgrades;

use App\Filament\Resources\Upgrades\Pages\ManageUpgrades;
use App\Models\Upgrade;
use App\Models\Client;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class UpgradeResource extends Resource
{
    protected static ?string $model = Upgrade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|\UnitEnum|null $navigationGroup = 'Кейсы';

    protected static ?string $navigationLabel = 'Апгрейды';

    protected static ?string $modelLabel = 'Апгрейд';

    protected static ?string $pluralModelLabel = 'Апгрейды';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->url(fn ($record) => route('filament.admin.resources.clients.edit', $record->client_id)),

                TextColumn::make('total_bet')
                    ->label('Ставка')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('target_price')
                    ->label('Цель')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('win_chance')
                    ->label('Шанс')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->sortable(),

                TextColumn::make('roll_value')
                    ->label('Roll')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->sortable(),

                TextColumn::make('result')
                    ->label('Результат')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'win' => 'success',
                        'lose' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'win' => 'Выигрыш',
                        'lose' => 'Проигрыш',
                        default => $state,
                    }),

                TextColumn::make('targetVirtualItem.name')
                    ->label('Целевой предмет')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->targetVirtualItem?->name),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('result')
                    ->label('Результат')
                    ->options([
                        'win' => 'Выигрыш',
                        'lose' => 'Проигрыш',
                    ]),

                SelectFilter::make('client_id')
                    ->label('Пользователь')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Client::where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value) => Client::find($value)?->name),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('С даты'),
                        DatePicker::make('until')
                            ->label('По дату'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('high_chance')
                    ->label('Высокий шанс (>50%)')
                    ->query(fn (Builder $query): Builder => $query->where('win_chance', '>', 50)),

                Filter::make('low_chance')
                    ->label('Низкий шанс (<20%)')
                    ->query(fn (Builder $query): Builder => $query->where('win_chance', '<', 20)),
            ])
            ->recordActions([
                // Только просмотр, без редактирования/удаления
            ])
            ->toolbarActions([
                // Без bulk actions
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUpgrades::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
