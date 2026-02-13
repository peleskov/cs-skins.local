<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\EditRecord;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $value = $data['value'] ?? '';
        $type = $data['type'] ?? SiteSetting::TYPE_STRING;

        $data['value_text'] = in_array($type, [SiteSetting::TYPE_STRING, SiteSetting::TYPE_JSON]) ? $value : '';
        $data['value_number'] = $type === SiteSetting::TYPE_NUMBER ? $value : '';
        $data['value_bool'] = $type === SiteSetting::TYPE_BOOLEAN && in_array($value, ['1', 1, true, 'true'], true);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
