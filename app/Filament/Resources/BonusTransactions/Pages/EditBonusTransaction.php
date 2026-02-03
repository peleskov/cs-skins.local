<?php

namespace App\Filament\Resources\BonusTransactions\Pages;

use App\Filament\Resources\BonusTransactions\BonusTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBonusTransaction extends EditRecord
{
    protected static string $resource = BonusTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
