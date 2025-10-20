<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SiteSettingResource;
use Filament\Actions;
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
        // Для boolean типа загружаем значение в boolean_value поле
        if (isset($data['type']) && $data['type'] === 'boolean') {
            $data['boolean_value'] = in_array($data['value'], ['1', 1, true, 'true'], true);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Если это boolean тип, переносим значение из boolean_value в value
        if (isset($data['type']) && $data['type'] === 'boolean' && isset($data['boolean_value'])) {
            $data['value'] = $data['boolean_value'] ? '1' : '0';
            unset($data['boolean_value']);
        }
        return $data;
    }
}
