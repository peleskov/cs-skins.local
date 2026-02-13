<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteSetting extends CreateRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? SiteSetting::TYPE_STRING;

        $data['value'] = match($type) {
            SiteSetting::TYPE_BOOLEAN => ($data['value_bool'] ?? false) ? '1' : '0',
            SiteSetting::TYPE_NUMBER => $data['value_number'] ?? '',
            default => $data['value_text'] ?? '',
        };

        unset($data['value_text'], $data['value_number'], $data['value_bool']);

        return $data;
    }
}
