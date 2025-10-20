<?php

namespace App\Filament\Resources\CaseCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CaseCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCaseCategories extends ListRecords
{
    protected static string $resource = CaseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
