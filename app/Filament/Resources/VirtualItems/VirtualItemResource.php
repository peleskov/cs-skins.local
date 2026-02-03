<?php

namespace App\Filament\Resources\VirtualItems;

use App\Filament\Resources\VirtualItems\Pages\ListVirtualItems;
use App\Filament\Resources\VirtualItems\Tables\VirtualItemsTable;
use App\Models\VirtualItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VirtualItemResource extends Resource
{
    protected static ?string $model = VirtualItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Виртуальные предметы';

    protected static ?string $modelLabel = 'Виртуальный предмет';

    protected static ?string $pluralModelLabel = 'Виртуальные предметы';

    protected static string|\UnitEnum|null $navigationGroup = 'Кейсы';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return VirtualItemsTable::configure($table);
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
            'index' => ListVirtualItems::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
