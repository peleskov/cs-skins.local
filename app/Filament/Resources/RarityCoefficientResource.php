<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RarityCoefficientResource\Pages\ListRarityCoefficients;
use App\Filament\Resources\RarityCoefficientResource\Pages\CreateRarityCoefficient;
use App\Filament\Resources\RarityCoefficientResource\Pages\EditRarityCoefficient;
use App\Filament\Resources\RarityCoefficientResource\Pages;
use App\Models\RarityCoefficient;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RarityCoefficientResource extends Resource
{
    protected static ?string $model = RarityCoefficient::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';
    
    protected static ?string $navigationLabel = 'Коэффициенты выкупа';
    
    protected static ?string $modelLabel = 'Коэффициент выкупа';
    
    protected static ?string $pluralModelLabel = 'Коэффициенты выкупа';
    
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('steam_name')
                            ->label('Steam идентификатор')
                            ->required()
                            ->disabled()
                            ->helperText('Системное название редкости в Steam'),
                            
                        TextInput::make('display_name_ru')
                            ->label('Название (Русский)')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('display_name_en')
                            ->label('Название (English)')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Section::make('Настройки выкупа')
                    ->schema([
                        TextInput::make('coefficient')
                            ->label('Коэффициент выкупа')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(1.00)
                            ->step(0.01)
                            ->required()
                            ->helperText('Процент от минимальной цены Steam (0.50 = 50%)')
                            ->suffix('%')
                            ->suffixIcon('heroicon-m-percent-badge'),
                            
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                            
                        Toggle::make('is_active')
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
                TextColumn::make('steam_name')
                    ->label('Steam ID')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('display_name_ru')
                    ->label('Редкость')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('coefficient')
                    ->label('Коэффициент')
                    ->formatStateUsing(fn ($state) => ($state * 100) . '%')
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 0.5 => 'success',
                        $state >= 0.3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                    
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                    
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                    
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность')
                    ->boolean()
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRarityCoefficients::route('/'),
            'create' => CreateRarityCoefficient::route('/create'),
            'edit' => EditRarityCoefficient::route('/{record}/edit'),
        ];
    }
}