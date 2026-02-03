<?php

namespace App\Filament\Resources\Promocodes\Pages;

use App\Filament\Resources\Promocodes\PromocodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromocode extends EditRecord
{
    protected static string $resource = PromocodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
