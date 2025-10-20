<?php

namespace App\Filament\Resources\BannedWordResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\BannedWordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBannedWord extends EditRecord
{
    protected static string $resource = BannedWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
