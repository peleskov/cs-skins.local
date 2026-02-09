<?php

namespace App\Filament\Resources\CaseModelResource\RelationManagers;

use App\Models\CaseItem;
use App\Models\CaseTier;
use App\Models\VirtualItem;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Предметы кейса';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tier_id')
                    ->label('Уровень')
                    ->options(fn () => $this->getOwnerRecord()->tiers()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('virtual_item_id')
                    ->label('Предмет')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        $existingIds = $this->getOwnerRecord()->items()->pluck('virtual_item_id')->toArray();
                        return VirtualItem::query()
                            ->whereNotNull('steam_price')
                            ->where('steam_price', '>', 0)
                            ->whereNotIn('id', $existingIds)
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('market_hash_name', 'like', "%{$search}%")
                                    ->orWhere('weapon_type', 'like', "%{$search}%");
                            })
                            ->orderBy('steam_price', 'desc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => "{$item->name} - \${$item->steam_price}"
                            ]);
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $item = VirtualItem::find($value);
                        return $item ? "{$item->name} - \${$item->steam_price}" : null;
                    })
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $item = VirtualItem::find($value);
                                if (!$item || !$item->steam_price || $item->steam_price <= 0) {
                                    $fail('Выбранный предмет недоступен (нет цены).');
                                    return;
                                }
                                $existingIds = $this->getOwnerRecord()->items()->pluck('virtual_item_id')->toArray();
                                if (in_array((int) $value, array_map('intval', $existingIds))) {
                                    $fail('Этот предмет уже добавлен в кейс.');
                                }
                            };
                        },
                    ])
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->hiddenOn('edit'),

                TextInput::make('price')
                    ->label('Цена в кейсе (₽)')
                    ->numeric()
                    ->suffix('₽')
                    ->required()
                    ->helperText(function (Get $get) {
                        if (!$get('virtual_item_id')) {
                            return 'Выберите предмет';
                        }
                        $item = VirtualItem::find($get('virtual_item_id'));
                        if (!$item?->steam_price) {
                            return 'Steam цена неизвестна';
                        }
                        return "Справочная Steam цена: \${$item->steam_price}";
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                ImageColumn::make('virtualItem.image_url')
                    ->label('')
                    ->width(50)
                    ->height(40),

                TextColumn::make('virtualItem.name')
                    ->label('Предмет')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('tier.name')
                    ->label('Уровень')
                    ->badge()
                    ->sortable(),

                TextColumn::make('virtualItem.weapon_type')
                    ->label('Тип')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('virtualItem.rarity')
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
                    }),

                TextColumn::make('virtualItem.steam_price')
                    ->label('Steam ($)')
                    ->money('USD')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('price')
                    ->label('Цена (₽)')
                    ->money('RUB')
                    ->sortable(),
            ])
            ->defaultSort('tier_id')
            ->filters([
                SelectFilter::make('tier_id')
                    ->label('Уровень')
                    ->options(fn () => $this->getOwnerRecord()->tiers()->pluck('name', 'id')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить предмет')
                    ->modalHeading(''),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(''),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
