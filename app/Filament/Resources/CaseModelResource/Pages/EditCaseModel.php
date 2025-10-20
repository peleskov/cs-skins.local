<?php

namespace App\Filament\Resources\CaseModelResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CaseModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCaseModel extends EditRecord
{
    protected static string $resource = CaseModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
