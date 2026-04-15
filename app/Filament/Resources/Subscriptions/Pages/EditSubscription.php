<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Services\SubscriptionService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $changes = $this->record->getChanges();
        $parts = [];

        if (isset($changes['expires_at'])) {
            $parts[] = "срок → {$this->record->expires_at->format('d.m.Y H:i')}";
        }
        if (isset($changes['is_active'])) {
            $parts[] = $this->record->is_active ? 'активирована' : 'деактивирована';
        }

        if (!empty($parts)) {
            SubscriptionService::log(
                $this->record,
                'updated',
                'Изменено администратором: ' . implode(', ', $parts),
                performedBy: auth()->user()?->email
            );
        }
    }
}
