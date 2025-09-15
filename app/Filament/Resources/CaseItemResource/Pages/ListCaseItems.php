<?php

namespace App\Filament\Resources\CaseItemResource\Pages;

use App\Filament\Resources\CaseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;

class ListCaseItems extends ListRecords
{
    protected static string $resource = CaseItemResource::class;
    
    public ?int $caseId = null;
    public ?int $tierId = null;
    public array $selectedItems = [];

    public function mount(): void
    {
        parent::mount();

        // Проверяем наличие обязательных параметров
        $this->caseId = request()->get('case_id') ?? 
                        request()->input('tableFilters.case_id.value') ?? 
                        null;
        $this->tierId = request()->get('tier_id') ?? 
                        request()->input('tableFilters.tier_id.value') ?? 
                        null;

        if (!$this->caseId || !$this->tierId) {
            abort(403, 'Нельзя отобразить список предметов без указания кейса и уровня. Доступ к этой странице возможен только через управление уровнями кейса.');
        }

        // Применяем фильтры из URL параметров
        $filters = [];

        if ($this->caseId) {
            $filters['case_id'] = ['value' => $this->caseId];
        }

        if ($this->tierId) {
            $filters['tier_id'] = ['value' => $this->tierId];
        }

        if (!empty($filters)) {
            $this->tableFilters = $filters;
        }
        
        // Загружаем текущие предметы уровня из БД
        $selectedIds = \App\Models\CaseItem::where([
            'case_id' => $this->caseId,
            'tier_id' => $this->tierId,
        ])->pluck('inventory_item_id')->toArray();
        
        $this->selectedItems = $selectedIds;
    }

    // Убираем getTableQuery так как теперь запрос формируется в самом ресурсе

    public function isTableSearchable(): bool
    {
        return false;
    }

    protected function shouldPersistTableFiltersInSession(): bool
    {
        return false;
    }

    protected function getTableFiltersFormWidth(): ?string
    {
        return null;
    }

    public function getSubheading(): HtmlString|string|null
    {
        $description = '';

        if($this->caseId){
            $case = \App\Models\CaseModel::find($this->caseId);
            if ($case) {
                $description = '<div class="text-gray-500 dark:text-gray-400" style="margin-bottom: 5px;">Кейс: ' . $case->name . '</div>';
            }
        }

        if($this->tierId){
            $tier = \App\Models\CaseTier::find($this->tierId);
            if ($tier) {
                $description .= '<div class="text-gray-500 dark:text-gray-400" style="margin-bottom: 5px;">Уровень: ' . $tier->name . ' (цена: ' . number_format($tier->price, 2) . ' ₽ • Вероятность: ' . $tier->probability . '%)</div>';
            }
        }
        return new HtmlString($description);
    }

    public function getHeading(): string
    {
        return 'Управление предметами уровня';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }



    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_items')
                ->label('Сохранить изменения')
                ->color('primary')
                ->action('saveItems'),
        ];
    }
    
    public function toggleItem($itemId)
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_filter($this->selectedItems, fn($id) => $id !== $itemId);
        } else {
            $this->selectedItems[] = $itemId;
        }
    }
    
    public function saveItems()
    {
        \App\Models\CaseItem::where([
            'case_id' => $this->caseId,
            'tier_id' => $this->tierId,
        ])->delete();

        foreach ($this->selectedItems as $itemId) {
            \App\Models\CaseItem::create([
                'case_id' => $this->caseId,
                'tier_id' => $this->tierId,
                'inventory_item_id' => $itemId,
            ]);
        }

        Notification::make()
            ->title('Предметы успешно сохранены')
            ->success()
            ->send();
    }
    
}
