<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaseItemResource\Pages;
use App\Models\CaseItem;
use App\Models\CaseTier;
use App\Models\ClientInventoryItem;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CaseItemResource extends Resource
{
    protected static ?string $model = CaseItem::class;

    protected static ?string $navigationIcon = 'heroicon-m-cube';
    
    protected static ?string $navigationLabel = 'Предметы кейсов';
    
    protected static ?string $modelLabel = 'Предмет кейса';
    
    protected static ?string $pluralModelLabel = 'Предметы кейсов';
    
    protected static ?string $navigationGroup = 'Маркетплейс';
    
    protected static ?int $navigationSort = 4;
    
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('case_id')
                    ->required(),
                    
                Forms\Components\Hidden::make('tier_id')
                    ->required(),
                    
                Forms\Components\Select::make('inventory_item_ids')
                    ->label(new HtmlString('Предметы из инвентаря бота <small class="text-gray-500 dark:text-gray-400">(отображается только первые 5 предметов, используйте поиск)</small>'))
                    ->autofocus(false)
                    ->native(false)
                    ->options(function () {
                        return ClientInventoryItem::whereHas('client', function ($query) {
                            $query->where('is_bot', true);
                        })
                        ->whereDoesntHave('caseItems')
                        ->limit(5)
                        ->pluck('item_name', 'id');
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        return ClientInventoryItem::whereHas('client', function ($query) {
                            $query->where('is_bot', true);
                        })
                        ->whereDoesntHave('caseItems')
                        ->where('item_name', 'like', "%{$search}%")
                        ->limit(5)
                        ->pluck('item_name', 'id');
                    })
                    ->multiple()
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.item_name')
                    ->label('Предмет')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventoryItem.float_value')
                    ->label('Float')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 4)),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaseItems::route('/'),
        ];
    }
}
