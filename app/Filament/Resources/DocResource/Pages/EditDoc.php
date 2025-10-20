<?php

namespace App\Filament\Resources\DocResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\DocResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDoc extends EditRecord
{
    protected static string $resource = DocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
