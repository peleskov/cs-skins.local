<?php

namespace App\Filament\Resources\TierItemsManagementResource\Pages;

use App\Filament\Resources\TierItemsManagementResource;
use App\Models\CaseTier;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTierItemsManagement extends ListRecords
{
    protected static string $resource = TierItemsManagementResource::class;

    public function getTitle(): string
    {
        $tierId = request()->route('tier');
        $tier = CaseTier::with('case')->find($tierId);
        
        return $tier 
            ? "Предметы уровня: {$tier->name} (Кейс: {$tier->case->name})"
            : 'Предметы уровня';
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
