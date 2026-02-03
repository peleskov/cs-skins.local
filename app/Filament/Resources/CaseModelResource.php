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
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CaseModelResource\RelationManagers\TiersRelationManager;
use App\Filament\Resources\CaseModelResource\RelationManagers\ItemsRelationManager;
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
                    ->suffix('₽'),
                TextInput::make('fund_percent')
                    ->label('% в фонд')
                    ->required()
                    ->numeric()
                    ->default(50)
                    ->suffix('%'),
                Select::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->options(CaseCategory::ordered()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                FileUpload::make('image_url')
                    ->label('Изображение')
                    ->image()
                    ->directory('cases')
                    ->visibility('public'),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                Select::make('case_type')
                    ->label('Тип кейса')
                    ->options(CaseModel::getTypes())
                    ->default('normal')
                    ->required()
                    ->live(),

                // Для бесплатных кейсов
                TextInput::make('free_min_deposit')
                    ->label('Мин. сумма депозитов')
                    ->numeric()
                    ->suffix('₽')
                    ->visible(fn (Get $get) => $get('case_type') === 'free'),
                TextInput::make('free_opens_count')
                    ->label('Кол-во бесплатных открытий')
                    ->numeric()
                    ->visible(fn (Get $get) => $get('case_type') === 'free'),

                // Для лимитированных кейсов
                DateTimePicker::make('available_until')
                    ->label('Доступен до')
                    ->visible(fn (Get $get) => $get('case_type') === 'limited'),
                TextInput::make('total_opens_limit')
                    ->label('Макс. открытий')
                    ->numeric()
                    ->visible(fn (Get $get) => $get('case_type') === 'limited'),

                // Метки
                Grid::make(3)
                    ->schema([
                        Toggle::make('label_hot')->label('HOT'),
                        Toggle::make('label_new')->label('NEW'),
                        Toggle::make('label_limited')->label('LIMITED'),
                    ]),

                Placeholder::make('tiers_info')
                    ->label('Уровни и предметы')
                    ->content('После создания кейса добавьте уровни и предметы на вкладках ниже')
                    ->hiddenOn('edit')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->description(fn ($record) => implode(' ', array_filter([
                        $record->label_hot ? '🔥 HOT' : null,
                        $record->label_new ? '✨ NEW' : null,
                        $record->label_limited ? '⏰ LIMITED' : null,
                    ]))),

                TextColumn::make('case_type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'normal' => 'gray',
                        'free' => 'success',
                        'limited' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => CaseModel::getTypes()[$state] ?? $state),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('accumulated_fund')
                    ->label('Фонд')
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tiers_count')
                    ->label('Уровни')
                    ->counts('tiers')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Предметы')
                    ->getStateUsing(fn ($record) => $record->items()->count())
                    ->sortable(),

                TextColumn::make('total_opens_count')
                    ->label('Открыто')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('case_type')
                    ->label('Тип')
                    ->options(CaseModel::getTypes()),
                SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name'),
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
            ItemsRelationManager::class,
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
