<?php

namespace App\Filament\Resources\CaseSecretLinks\Pages;

use App\Filament\Resources\CaseSecretLinks\CaseSecretLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCaseSecretLinks extends ManageRecords
{
    protected static string $resource = CaseSecretLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Создать ссылку'),
        ];
    }
}
