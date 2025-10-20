<?php

namespace App\Filament\Resources\ChatBanResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ChatBanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatBan extends EditRecord
{
    protected static string $resource = ChatBanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
