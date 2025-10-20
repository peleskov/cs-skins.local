<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CaseModelResource\RelationManagers\TiersRelationManager;
use App\Filament\Resources\CaseModelResource\Pages\ListCaseModels;
use App\Filament\Resources\CaseModelResource\Pages\CreateCaseModel;
use App\Filament\Resources\CaseModelResource\Pages\EditCaseModel;
use App\Filament\Resources\CaseModelResource\Pages;
use App\Filament\Resources\CaseModelResource\RelationManagers;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CaseModelResource extends Resource
{
    protected static ?string $model = CaseModel::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Кейсы';

    protected static ?string $modelLabel = 'Кейс';

    protected static ?string $pluralModelLabel = 'Кейсы';

    protected static string | \UnitEnum | null $navigationGroup = 'Кейсы';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(string $operation, $state, Set $set) =>
                        $operation === 'create' ? $set('slug', Str::slug($state)) : null
                    ),
                TextInput::make('slug')
                    ->label('URL')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->dehydrated(),
                Textarea::make('description')
                    ->label('Описание')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Цена')
                    ->required()
                    ->numeric()
                    ->prefix('₽'),
                TextInput::make('fund_percent')
                    ->label('Процент в фонд')
                    ->required()
                    ->numeric()
                    ->default(50)
                    ->suffix('%'),
                Grid::make(2)
                    ->schema([
                        FileUpload::make('image_url')
                            ->label('Изображение')
                            ->image()
                            ->directory('cases')
                            ->visibility('public')
                            ->columnSpan(1),
                        Grid::make(1)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Категория')
                                    ->relationship('category', 'name')
                                    ->options(CaseCategory::ordered()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                            ])
                            ->columnSpan(1),
                    ]),
                Placeholder::make('tiers_info')
                    ->label('Уровни призов')
                    ->content('После создания кейса вы сможете добавить уровни на странице редактирования')
                    ->hiddenOn('edit')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s') // Обновляем таблицу каждые 10 секунд
            ->columns([
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('accumulated_fund')
                    ->label('Фонд')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('fund_percent')
                    ->label('% в фонд')
                    ->suffix('%'),
                TextColumn::make('tiers_count')
                    ->label('Уровни')
                    ->counts('tiers')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Предметы')
                    ->getStateUsing(function ($record) {
                        return $record->items()->count();
                    })
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseModels::route('/'),
            'create' => CreateCaseModel::route('/create'),
            'edit' => EditCaseModel::route('/{record}/edit'),
        ];
    }
}
