<?php

namespace App\Filament\Resources\RarityCoefficientResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RarityCoefficientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRarityCoefficient extends EditRecord
{
    protected static string $resource = RarityCoefficientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
