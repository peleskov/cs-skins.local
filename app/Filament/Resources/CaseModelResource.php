<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaseModelResource\Pages;
use App\Filament\Resources\CaseModelResource\RelationManagers;
use App\Models\CaseModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CaseModelResource extends Resource
{
    protected static ?string $model = CaseModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Кейсы';
    
    protected static ?string $modelLabel = 'Кейс';
    
    protected static ?string $pluralModelLabel = 'Кейсы';
    
    protected static ?string $navigationGroup = 'Маркетплейс';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                        $operation === 'create' ? $set('slug', Str::slug($state)) : null
                    ),
                Forms\Components\TextInput::make('slug')
                    ->label('URL')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->dehydrated(),
                Forms\Components\Textarea::make('description')
                    ->label('Описание')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->label('Цена')
                    ->required()
                    ->numeric()
                    ->prefix('₽'),
                Forms\Components\TextInput::make('fund_percent')
                    ->label('Процент в фонд')
                    ->required()
                    ->numeric()
                    ->default(50)
                    ->suffix('%'),
                Forms\Components\FileUpload::make('image_url')
                    ->label('Изображение')
                    ->image()
                    ->directory('cases')
                    ->visibility('public'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
                Forms\Components\Placeholder::make('tiers_info')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('accumulated_fund')
                    ->label('Фонд')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fund_percent')
                    ->label('% в фонд')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('tiers_count')
                    ->label('Уровни')
                    ->counts('tiers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Предметы')
                    ->getStateUsing(function ($record) {
                        return $record->items()->count();
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaseModels::route('/'),
            'create' => Pages\CreateCaseModel::route('/create'),
            'edit' => Pages\EditCaseModel::route('/{record}/edit'),
        ];
    }
}
