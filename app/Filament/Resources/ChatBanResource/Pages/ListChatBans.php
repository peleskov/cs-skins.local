<?php

namespace App\Filament\Resources\ChatBanResource\Pages;

use App\Filament\Resources\ChatBanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatBans extends ListRecords
{
    protected static string $resource = ChatBanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
