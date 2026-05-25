<?php

namespace App\Filament\Resources\ClientResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
