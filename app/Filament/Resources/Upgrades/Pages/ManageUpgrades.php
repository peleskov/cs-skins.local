<?php

namespace App\Filament\Resources\Upgrades\Pages;

use App\Filament\Resources\Upgrades\UpgradeResource;
use App\Filament\Resources\Upgrades\Widgets\UpgradeStatsWidget;
use Filament\Resources\Pages\ManageRecords;

class ManageUpgrades extends ManageRecords
{
    protected static string $resource = UpgradeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpgradeStatsWidget::class,
        ];
    }
}
