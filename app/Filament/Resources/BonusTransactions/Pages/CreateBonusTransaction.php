<?php

namespace App\Filament\Resources\BonusTransactions\Pages;

use App\Filament\Resources\BonusTransactions\BonusTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBonusTransaction extends CreateRecord
{
    protected static string $resource = BonusTransactionResource::class;
}
