<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'CS2 Предметы';

    protected static ?string $modelLabel = 'Предмет';

    protected static ?string $pluralModelLabel = 'Предметы';

    protected static ?string $navigationGroup = 'Маркетплейс';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('steam_market_hash_name')
                            ->label('Steam Market Hash Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('name_ru')
                            ->label('Название (RU)')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('name_en')
                            ->label('Название (EN)')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options([
                                Item::TYPE_KNIFE => 'Ножи',
                                Item::TYPE_PISTOL => 'Пистолеты',
                                Item::TYPE_RIFLE => 'Автоматы',
                                Item::TYPE_SMG => 'Пистолеты-пулемёты',
                                Item::TYPE_SHOTGUN => 'Дробовики',
                                Item::TYPE_MACHINEGUN => 'Пулемёты',
                                Item::TYPE_SNIPER => 'Снайперские винтовки',
                                Item::TYPE_GLOVES => 'Перчатки',
                                Item::TYPE_STICKER => 'Наклейки',
                                Item::TYPE_GRAFFITI => 'Граффити',
                                Item::TYPE_CASE => 'Кейсы',
                                Item::TYPE_KEY => 'Ключи',
                                Item::TYPE_MUSIC_KIT => 'Музыкальные наборы',
                                Item::TYPE_AGENT => 'Агенты',
                                Item::TYPE_PASS => 'Пропуски',
                            ])
                            ->required(),
                        
                        Forms\Components\TextInput::make('weapon')
                            ->label('Оружие')
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('rarity')
                            ->label('Редкость')
                            ->options([
                                Item::RARITY_CONSUMER => 'Ширпотреб',
                                Item::RARITY_INDUSTRIAL => 'Промышленное качество',
                                Item::RARITY_MIL_SPEC => 'Армейское качество',
                                Item::RARITY_RESTRICTED => 'Запрещённое',
                                Item::RARITY_CLASSIFIED => 'Засекреченное',
                                Item::RARITY_COVERT => 'Тайное',
                                Item::RARITY_CONTRABAND => 'Контрабанда',
                            ])
                            ->required(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Изображение и цены')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')
                            ->label('URL изображения')
                            ->url()
                            ->required(),
                        
                        Forms\Components\TextInput::make('min_steam_price')
                            ->label('Минимальная цена Steam')
                            ->numeric()
                            ->step(0.01),
                        
                        Forms\Components\TextInput::make('steam_listings_count')
                            ->label('Количество лотов на Steam')
                            ->numeric()
                            ->default(0),
                        
                        Forms\Components\Toggle::make('is_valid')
                            ->label('Валидный для выкупа ботом')
                            ->default(false),
                        
                        Forms\Components\TextInput::make('buyout_coefficient')
                            ->label('Коэффициент выкупа')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1),
                    ])->columns(2),
                
                Forms\Components\Section::make('Дополнительно')
                    ->schema([
                        Forms\Components\Textarea::make('description_ru')
                            ->label('Описание (RU)')
                            ->rows(3),
                        
                        Forms\Components\Textarea::make('description_en')
                            ->label('Описание (EN)')
                            ->rows(3),
                        
                        Forms\Components\TagsInput::make('tags')
                            ->label('Теги')
                            ->placeholder('StatTrak, Souvenir, etc'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Изображение')
                    ->size(60)
                    ->square(),
                
                Tables\Columns\TextColumn::make('name_ru')
                    ->label('Название')
                    ->searchable()
                    ->weight(FontWeight::Medium),
                
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Тип')
                    ->colors([
                        'primary' => Item::TYPE_KNIFE,
                        'success' => Item::TYPE_RIFLE,
                        'warning' => Item::TYPE_PISTOL,
                        'danger' => Item::TYPE_SNIPER,
                        'secondary' => [Item::TYPE_SMG, Item::TYPE_SHOTGUN, Item::TYPE_MACHINEGUN],
                        'gray' => [Item::TYPE_GLOVES, Item::TYPE_STICKER, Item::TYPE_GRAFFITI],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Item::TYPE_KNIFE => 'Ножи',
                        Item::TYPE_PISTOL => 'Пистолеты',
                        Item::TYPE_RIFLE => 'Автоматы',
                        Item::TYPE_SMG => 'ПП',
                        Item::TYPE_SHOTGUN => 'Дробовики',
                        Item::TYPE_MACHINEGUN => 'Пулемёты',
                        Item::TYPE_SNIPER => 'Снайперские',
                        Item::TYPE_GLOVES => 'Перчатки',
                        Item::TYPE_STICKER => 'Наклейки',
                        Item::TYPE_GRAFFITI => 'Граффити',
                        Item::TYPE_CASE => 'Кейсы',
                        Item::TYPE_KEY => 'Ключи',
                        Item::TYPE_MUSIC_KIT => 'Музыка',
                        Item::TYPE_AGENT => 'Агенты',
                        Item::TYPE_PASS => 'Пропуски',
                        default => $state,
                    }),
                
                Tables\Columns\BadgeColumn::make('rarity')
                    ->label('Редкость')
                    ->colors([
                        'gray' => Item::RARITY_CONSUMER,
                        'blue' => Item::RARITY_INDUSTRIAL,
                        'indigo' => Item::RARITY_MIL_SPEC,
                        'purple' => Item::RARITY_RESTRICTED,
                        'pink' => Item::RARITY_CLASSIFIED,
                        'red' => Item::RARITY_COVERT,
                        'yellow' => Item::RARITY_CONTRABAND,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Item::RARITY_CONSUMER => 'Ширпотреб',
                        Item::RARITY_INDUSTRIAL => 'Промышленное',
                        Item::RARITY_MIL_SPEC => 'Армейское',
                        Item::RARITY_RESTRICTED => 'Запрещённое',
                        Item::RARITY_CLASSIFIED => 'Засекреченное',
                        Item::RARITY_COVERT => 'Тайное',
                        Item::RARITY_CONTRABAND => 'Контрабанда',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('min_steam_price')
                    ->label('Цена Steam')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('steam_listings_count')
                    ->label('Лотов')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_valid')
                    ->label('Валидный')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
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
                
                Tables\Filters\SelectFilter::make('rarity')
                    ->label('Редкость')
                    ->options([
                        Item::RARITY_CONSUMER => 'Ширпотреб',
                        Item::RARITY_INDUSTRIAL => 'Промышленное',
                        Item::RARITY_MIL_SPEC => 'Армейское',
                        Item::RARITY_RESTRICTED => 'Запрещённое',
                        Item::RARITY_CLASSIFIED => 'Засекреченное',
                        Item::RARITY_COVERT => 'Тайное',
                        Item::RARITY_CONTRABAND => 'Контрабанда',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_valid')
                    ->label('Валидный для выкупа')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
