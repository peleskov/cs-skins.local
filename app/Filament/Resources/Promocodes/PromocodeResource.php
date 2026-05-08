<?php

namespace App\Filament\Resources\Promocodes;

use App\Filament\Resources\Promocodes\Pages\CreatePromocode;
use App\Filament\Resources\Promocodes\Pages\EditPromocode;
use App\Filament\Resources\Promocodes\Pages\ListPromocodes;
use App\Filament\Resources\Promocodes\Schemas\PromocodeForm;
use App\Filament\Resources\Promocodes\Tables\PromocodesTable;
use App\Models\Promocode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PromocodeResource extends Resource
{
    protected static ?string $model = Promocode::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Промокоды';

    protected static ?string $modelLabel = 'Промокод';

    protected static ?string $pluralModelLabel = 'Промокоды';

    protected static string|\UnitEnum|null $navigationGroup = 'Кейсы';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PromocodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromocodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Promocodes\RelationManagers\ActivationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromocodes::route('/'),
            'create' => CreatePromocode::route('/create'),
            'edit' => EditPromocode::route('/{record}/edit'),
        ];
    }
}
