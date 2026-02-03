<?php

namespace App\Filament\Resources\Promocodes\Pages;

use App\Filament\Resources\Promocodes\PromocodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromocodes extends ListRecords
{
    protected static string $resource = PromocodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
