<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CaseCategoryResource\Pages\ListCaseCategories;
use App\Filament\Resources\CaseCategoryResource\Pages\CreateCaseCategory;
use App\Filament\Resources\CaseCategoryResource\Pages\EditCaseCategory;
use App\Filament\Resources\CaseCategoryResource\Pages;
use App\Filament\Resources\CaseCategoryResource\RelationManagers;
use App\Models\CaseCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CaseCategoryResource extends Resource
{
    protected static ?string $model = CaseCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Категории кейсов';

    protected static ?string $modelLabel = 'Категория кейсов';

    protected static ?string $pluralModelLabel = 'Категории кейсов';

    protected static string | \UnitEnum | null $navigationGroup = 'Кейсы';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('icon')
                    ->label('Иконка')
                    ->image()
                    ->directory('case-categories')
                    ->visibility('public'),

                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Чем меньше число, тем выше в списке'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->label('Иконка')
                    ->square()
                    ->size(40),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('cases_count')
                    ->label('Кол-во кейсов')
                    ->counts('cases'),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListCaseCategories::route('/'),
            'create' => CreateCaseCategory::route('/create'),
            'edit' => EditCaseCategory::route('/{record}/edit'),
        ];
    }
}
