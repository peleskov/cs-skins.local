<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClientResource::makeBlockAction('withdraw', 'Вывод', 'heroicon-o-arrow-up-tray'),
            ClientResource::makeBlockAction('purchases', 'Предметы', 'heroicon-o-shopping-cart'),
            ClientResource::makeBlockAction('balance', 'Баланс', 'heroicon-o-banknotes'),
        ];
    }
}
