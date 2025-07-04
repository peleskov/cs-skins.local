<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqCategoryResource\Pages;
use App\Filament\Resources\FaqCategoryResource\RelationManagers;
use App\Models\FaqCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class FaqCategoryResource extends Resource
{
    protected static ?string $model = FaqCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    
    protected static ?string $navigationLabel = 'Категории FAQ';
    
    protected static ?string $modelLabel = 'Категория';
    
    protected static ?string $pluralModelLabel = 'Категории FAQ';
    
    protected static ?string $navigationGroup = 'Контент';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                    
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(FaqCategory::class, 'slug', ignoreRecord: true)
                    ->rules(['alpha_dash'])
                    ->helperText('Используется в URL. Будет создан автоматически из названия.'),
                    
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Slug скопирован!')
                    ->badge(),
                    
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                    
                TextColumn::make('faqs_count')
                    ->label('Количество вопросов')
                    ->counts('faqs')
                    ->badge(),
                    
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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
            ->defaultSort('sort_order');
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
            'index' => Pages\ListFaqCategories::route('/'),
            'create' => Pages\CreateFaqCategory::route('/create'),
            'edit' => Pages\EditFaqCategory::route('/{record}/edit'),
        ];
    }
}
