<?php

namespace App\Filament\Resources\BonusTransactions\Pages;

use App\Filament\Resources\BonusTransactions\BonusTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBonusTransactions extends ListRecords
{
    protected static string $resource = BonusTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
