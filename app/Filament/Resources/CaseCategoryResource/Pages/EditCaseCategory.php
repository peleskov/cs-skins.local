<?php

namespace App\Filament\Resources\CaseCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CaseCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCaseCategory extends EditRecord
{
    protected static string $resource = CaseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
