<?php

namespace App\Filament\Resources\BannedWordResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\BannedWordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBannedWords extends ListRecords
{
    protected static string $resource = BannedWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
