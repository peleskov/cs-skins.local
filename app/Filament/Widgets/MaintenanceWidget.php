<?php

namespace App\Filament\Widgets;

use App\Models\SiteSetting;
use Filament\Widgets\Widget;
use Filament\Notifications\Notification;

class MaintenanceWidget extends Widget
{
    protected string $view = 'filament.widgets.maintenance-widget';

    protected static ?int $sort = -10;

    public bool $maintenanceMode = false;

    public function mount(): void
    {
        $this->maintenanceMode = (bool) SiteSetting::get('maintenance_mode', false);
    }

    public function toggleMaintenance(): void
    {
        $this->maintenanceMode = !$this->maintenanceMode;
        SiteSetting::set('maintenance_mode', $this->maintenanceMode, SiteSetting::TYPE_BOOLEAN, 'Режим технических работ');

        Notification::make()
            ->title($this->maintenanceMode ? 'Режим тех. работ включен' : 'Режим тех. работ выключен')
            ->icon($this->maintenanceMode ? 'heroicon-o-wrench-screwdriver' : 'heroicon-o-check-circle')
            ->success()
            ->send();
    }
}
