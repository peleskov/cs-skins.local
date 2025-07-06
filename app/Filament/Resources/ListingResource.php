<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ListingResource\Pages;
use App\Filament\Resources\ListingResource\RelationManagers;
use App\Models\Listing;
use App\Models\Item;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;

class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Активные предложения';

    protected static ?string $modelLabel = 'Предложение';

    protected static ?string $pluralModelLabel = 'Предложения';

    protected static ?string $navigationGroup = 'Маркетплейс';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\Select::make('item_id')
                            ->label('Предмет')
                            ->relationship('item', 'name_ru')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('seller_id')
                            ->label('Продавец')
                            ->relationship('seller', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                Listing::STATUS_ACTIVE => 'Активно',
                                Listing::STATUS_SOLD => 'Продано',
                                Listing::STATUS_CANCELLED => 'Отменено',
                                Listing::STATUS_EXPIRED => 'Истекло',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Характеристики предмета')
                    ->schema([
                        Forms\Components\TextInput::make('wear_value')
                            ->label('Износ (float)')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0)
                            ->maxValue(1),

                        Forms\Components\TextInput::make('pattern_index')
                            ->label('Индекс паттерна')
                            ->numeric(),

                        Forms\Components\TextInput::make('name_tag')
                            ->label('Именной ярлык')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_stattrak')
                            ->label('StatTrak'),

                        Forms\Components\Toggle::make('is_souvenir')
                            ->label('Сувенир'),

                        Forms\Components\KeyValue::make('stickers')
                            ->label('Наклейки')
                            ->keyLabel('Позиция')
                            ->valueLabel('Наклейка'),
                    ])->columns(2),

                Forms\Components\Section::make('Даты')
                    ->schema([
                        Forms\Components\DateTimePicker::make('listed_at')
                            ->label('Дата выставления')
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Истекает'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('item.image_url')
                    ->label('Изображение')
                    ->size(60)
                    ->square(),

                Tables\Columns\TextColumn::make('item.name_ru')
                    ->label('Предмет')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->limit(30),

                Tables\Columns\TextColumn::make('seller.name')
                    ->label('Продавец')
                    ->searchable()
                    ->badge()
                    ->color(fn (Listing $record): string => $record->seller->isBot() ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->colors([
                        'success' => Listing::STATUS_ACTIVE,
                        'gray' => Listing::STATUS_SOLD,
                        'danger' => Listing::STATUS_CANCELLED,
                        'warning' => Listing::STATUS_EXPIRED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Listing::STATUS_ACTIVE => 'Активно',
                        Listing::STATUS_SOLD => 'Продано',
                        Listing::STATUS_CANCELLED => 'Отменено',
                        Listing::STATUS_EXPIRED => 'Истекло',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('wear_value')
                    ->label('Износ')
                    ->formatStateUsing(fn (?float $state): string => 
                        $state ? number_format($state, 3) : '—'
                    )
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state <= 0.07 => 'success',
                        $state <= 0.15 => 'primary',
                        $state <= 0.38 => 'warning',
                        $state <= 0.45 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_stattrak')
                    ->label('ST')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon(''),

                Tables\Columns\TextColumn::make('listed_at')
                    ->label('Выставлено')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('item.rarity')
                    ->label('Редкость')
                    ->badge()
                    ->colors([
                        'gray' => Item::RARITY_CONSUMER,
                        'blue' => Item::RARITY_INDUSTRIAL,
                        'indigo' => Item::RARITY_MIL_SPEC,
                        'purple' => Item::RARITY_RESTRICTED,
                        'pink' => Item::RARITY_CLASSIFIED,
                        'red' => Item::RARITY_COVERT,
                        'yellow' => Item::RARITY_CONTRABAND,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Listing::STATUS_ACTIVE => 'Активно',
                        Listing::STATUS_SOLD => 'Продано',
                        Listing::STATUS_CANCELLED => 'Отменено',
                        Listing::STATUS_EXPIRED => 'Истекло',
                    ])
                    ->default(Listing::STATUS_ACTIVE),

                Tables\Filters\SelectFilter::make('item.type')
                    ->label('Тип предмета')
                    ->relationship('item', 'type')
                    ->options([
                        Item::TYPE_KNIFE => 'Ножи',
                        Item::TYPE_PISTOL => 'Пистолеты',
                        Item::TYPE_RIFLE => 'Автоматы',
                        Item::TYPE_SMG => 'Пистолеты-пулемёты',
                        Item::TYPE_SHOTGUN => 'Дробовики',
                        Item::TYPE_MACHINEGUN => 'Пулемёты',
                        Item::TYPE_SNIPER => 'Снайперские винтовки',
                        Item::TYPE_GLOVES => 'Перчатки',
                    ]),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->label('Цена от')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('price_to')
                            ->label('Цена до')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('price', '<=', $price),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_stattrak')
                    ->label('StatTrak')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('listed_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListings::route('/'),
            'create' => Pages\CreateListing::route('/create'),
            'edit' => Pages\EditListing::route('/{record}/edit'),
        ];
    }
}