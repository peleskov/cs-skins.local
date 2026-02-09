<?php

namespace App\Filament\Resources\CaseModelResource\RelationManagers;

use App\Models\CaseItem;
use App\Models\CaseTier;
use App\Models\VirtualItem;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    protected function applyItemFilters(Builder $query, Get $get): Builder
    {
        $existingIds = $this->getOwnerRecord()->items()->pluck('virtual_item_id')->toArray();

        $query->whereNotNull('steam_price')
            ->where('steam_price', '>', 0)
            ->whereNotIn('id', $existingIds);

        if ($rarity = $get('filter_rarity')) {
            $query->where('rarity', $rarity);
        }
        if ($weaponType = $get('filter_weapon_type')) {
            $query->where('weapon_type', $weaponType);
        }
        if ($priceFrom = $get('filter_price_from')) {
            $query->where('steam_price', '>=', (float) $priceFrom);
        }
        if ($priceTo = $get('filter_price_to')) {
            $query->where('steam_price', '<=', (float) $priceTo);
        }

        return $query;
    }

    protected function formatItemOption(VirtualItem $item): string
    {
        return "{$item->name} — \${$item->steam_price}";
    }

    public function form(Schema $schema): Schema
    {
        $filterStateUpdated = function (Set $set) {
            $set('virtual_item_id', null);
        };

        return $schema
            ->components([
                Select::make('tier_id')
                    ->label('Уровень')
                    ->options(fn () => $this->getOwnerRecord()->tiers()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Grid::make(2)
                    ->schema([
                        Select::make('filter_rarity')
                            ->label('Редкость')
                            ->options(
                                collect(VirtualItem::getRarities())
                                    ->mapWithKeys(fn ($r) => [$r => $r])
                            )
                            ->placeholder('Все')
                            ->live()
                            ->afterStateUpdated($filterStateUpdated)
                            ->dehydrated(false)
                            ->hiddenOn('edit'),

                        Select::make('filter_weapon_type')
                            ->label('Тип оружия')
                            ->options(
                                fn () => VirtualItem::query()
                                    ->whereNotNull('weapon_type')
                                    ->distinct()
                                    ->orderBy('weapon_type')
                                    ->pluck('weapon_type', 'weapon_type')
                            )
                            ->searchable()
                            ->placeholder('Все')
                            ->live()
                            ->afterStateUpdated($filterStateUpdated)
                            ->dehydrated(false)
                            ->hiddenOn('edit'),

                        TextInput::make('filter_price_from')
                            ->label('Цена от ($)')
                            ->numeric()
                            ->live(debounce: 500)
                            ->afterStateUpdated($filterStateUpdated)
                            ->dehydrated(false)
                            ->hiddenOn('edit'),

                        TextInput::make('filter_price_to')
                            ->label('Цена до ($)')
                            ->numeric()
                            ->live(debounce: 500)
                            ->afterStateUpdated($filterStateUpdated)
                            ->dehydrated(false)
                            ->hiddenOn('edit'),
                    ])
                    ->hiddenOn('edit'),

                Select::make('virtual_item_id')
                    ->label('Предмет')
                    ->searchable()
                    ->options(function (Get $get) {
                        $query = $this->applyItemFilters(VirtualItem::query(), $get);

                        return $query->orderBy('steam_price', 'desc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => $this->formatItemOption($item),
                            ]);
                    })
                    ->getSearchResultsUsing(function (string $search, Get $get) {
                        $query = $this->applyItemFilters(VirtualItem::query(), $get);

                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('market_hash_name', 'like', "%{$search}%");
                        });

                        return $query->orderBy('steam_price', 'desc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => $this->formatItemOption($item),
                            ]);
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $item = VirtualItem::find($value);
                        return $item ? $this->formatItemOption($item) : null;
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
