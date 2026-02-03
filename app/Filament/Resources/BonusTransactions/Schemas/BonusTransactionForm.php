<?php

namespace App\Filament\Resources\BonusTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BonusTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->required(),
                Select::make('type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit'])
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('description'),
                Select::make('promocode_id')
                    ->relationship('promocode', 'id'),
                Select::make('payment_id')
                    ->relationship('payment', 'id'),
                Select::make('case_id')
                    ->relationship('case', 'name'),
            ]);
    }
}
