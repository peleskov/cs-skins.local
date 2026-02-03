<?php

namespace App\Filament\Resources\BonusTransactions;

use App\Filament\Resources\BonusTransactions\Pages\ListBonusTransactions;
use App\Filament\Resources\BonusTransactions\Tables\BonusTransactionsTable;
use App\Models\BonusTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BonusTransactionResource extends Resource
{
    protected static ?string $model = BonusTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Бонусные транзакции';

    protected static ?string $modelLabel = 'Бонусная транзакция';

    protected static ?string $pluralModelLabel = 'Бонусные транзакции';

    protected static string|\UnitEnum|null $navigationGroup = 'Пользователи';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return BonusTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBonusTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
