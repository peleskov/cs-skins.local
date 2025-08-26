<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RarityCoefficientResource\Pages;
use App\Models\RarityCoefficient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RarityCoefficientResource extends Resource
{
    protected static ?string $model = RarityCoefficient::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    
    protected static ?string $navigationGroup = 'Настройки';
    
    protected static ?string $navigationLabel = 'Коэффициенты выкупа';
    
    protected static ?string $modelLabel = 'Коэффициент выкупа';
    
    protected static ?string $pluralModelLabel = 'Коэффициенты выкупа';
    
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('steam_name')
                            ->label('Steam идентификатор')
                            ->required()
                            ->disabled()
                            ->helperText('Системное название редкости в Steam'),
                            
                        Forms\Components\TextInput::make('display_name_ru')
                            ->label('Название (Русский)')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('display_name_en')
                            ->label('Название (English)')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Настройки выкупа')
                    ->schema([
                        Forms\Components\TextInput::make('coefficient')
                            ->label('Коэффициент выкупа')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(1.00)
                            ->step(0.01)
                            ->required()
                            ->helperText('Процент от минимальной цены Steam (0.50 = 50%)')
                            ->suffix('%')
                            ->suffixIcon('heroicon-m-percent-badge'),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->helperText('Неактивные коэффициенты не используются при расчете'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('steam_name')
                    ->label('Steam ID')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('display_name_ru')
                    ->label('Редкость')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('coefficient')
                    ->label('Коэффициент')
                    ->formatStateUsing(fn ($state) => ($state * 100) . '%')
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 0.5 => 'success',
                        $state >= 0.3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активность')
                    ->boolean()
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRarityCoefficients::route('/'),
            'create' => Pages\CreateRarityCoefficient::route('/create'),
            'edit' => Pages\EditRarityCoefficient::route('/{record}/edit'),
        ];
    }
}