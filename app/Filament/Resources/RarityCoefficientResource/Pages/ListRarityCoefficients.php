<?php

namespace App\Filament\Resources\RarityCoefficientResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RarityCoefficientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRarityCoefficients extends ListRecords
{
    protected static string $resource = RarityCoefficientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
