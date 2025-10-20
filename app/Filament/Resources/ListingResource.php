<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ListingResource\Pages\ListListings;
use App\Filament\Resources\ListingResource\Pages\CreateListing;
use App\Filament\Resources\ListingResource\Pages\EditListing;
use App\Filament\Resources\ListingResource\Pages;
use App\Filament\Resources\ListingResource\RelationManagers;
use App\Models\Listing;
use App\Models\Client;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;

class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Активные предложения';

    protected static ?string $modelLabel = 'Предложение';

    protected static ?string $pluralModelLabel = 'Предложения';

    protected static string | \UnitEnum | null $navigationGroup = 'Маркетплейс';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        Select::make('seller_id')
                            ->label('Продавец')
                            ->relationship('seller', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required(),

                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                Listing::STATUS_ACTIVE => 'Активно',
                                Listing::STATUS_SOLD => 'Продано',
                                Listing::STATUS_CANCELLED => 'Отменено',
                                Listing::STATUS_EXPIRED => 'Истекло',
                            ])
                            ->required(),
                    ])->columns(2),

                Section::make('Характеристики предмета')
                    ->schema([
                        TextInput::make('wear_value')
                            ->label('Износ (float)')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0)
                            ->maxValue(1),

                        TextInput::make('pattern_index')
                            ->label('Индекс паттерна')
                            ->numeric(),

                        TextInput::make('name_tag')
                            ->label('Именной ярлык')
                            ->maxLength(255),

                        Toggle::make('is_stattrak')
                            ->label('StatTrak'),

                        Toggle::make('is_souvenir')
                            ->label('Сувенир'),

                        KeyValue::make('stickers')
                            ->label('Наклейки')
                            ->keyLabel('Позиция')
                            ->valueLabel('Наклейка'),
                    ])->columns(2),

                Section::make('Даты')
                    ->schema([
                        DateTimePicker::make('listed_at')
                            ->label('Дата выставления')
                            ->default(now()),

                        DateTimePicker::make('expires_at')
                            ->label('Истекает'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('seller.name')
                    ->label('Продавец')
                    ->searchable()
                    ->badge()
                    ->color(fn (Listing $record): string => $record->seller->isBot() ? 'warning' : 'primary'),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                BadgeColumn::make('status')
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

                TextColumn::make('wear_value')
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

                IconColumn::make('is_stattrak')
                    ->label('ST')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon(''),

                TextColumn::make('listed_at')
                    ->label('Выставлено')
                    ->dateTime()
                    ->sortable()
                    ->since(),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Listing::STATUS_ACTIVE => 'Активно',
                        Listing::STATUS_SOLD => 'Продано',
                        Listing::STATUS_CANCELLED => 'Отменено',
                        Listing::STATUS_EXPIRED => 'Истекло',
                    ])
                    ->default(Listing::STATUS_ACTIVE),


                Filter::make('price_range')
                    ->schema([
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
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('price', '<=', $price),
                            );
                    }),

                TernaryFilter::make('is_stattrak')
                    ->label('StatTrak')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListListings::route('/'),
            'create' => CreateListing::route('/create'),
            'edit' => EditListing::route('/{record}/edit'),
        ];
    }
}