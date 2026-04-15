<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Services\SubscriptionService;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['settings'])) {
            $data['settings'] = (new SubscriptionService())->getDefaultSettings();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        SubscriptionService::log(
            $this->record,
            'created',
            'Подписка создана администратором',
            performedBy: auth()->user()?->email
        );
    }
}
