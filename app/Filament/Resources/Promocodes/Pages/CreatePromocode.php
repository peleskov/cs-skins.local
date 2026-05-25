<?php

namespace App\Filament\Resources\Promocodes\Pages;

use App\Filament\Resources\Promocodes\PromocodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromocode extends CreateRecord
{
    protected static string $resource = PromocodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_admin_id'] = auth()->id();

        return $data;
    }
}
