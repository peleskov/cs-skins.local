<?php

namespace App\Filament\Resources\CaseItemResource\Pages;

use App\Filament\Resources\CaseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListCaseItems extends ListRecords
{
    protected static string $resource = CaseItemResource::class;
    

    public function mount(): void
    {
        parent::mount();

        // Проверяем наличие обязательных параметров
        $caseId = request()->get('case_id') ?? request()->input('tableFilters.case_id.value');
        $tierId = request()->get('tier_id') ?? request()->input('tableFilters.tier_id.value');

        if (!$caseId || !$tierId) {
            abort(403, 'Нельзя отобразить список предметов без указания кейса и уровня. Доступ к этой странице возможен только через управление уровнями кейса.');
        }

        // Применяем фильтры из URL параметров
        $filters = [];

        if (request()->has('case_id')) {
            $filters['case_id'] = ['value' => request()->get('case_id')];
        }

        if (request()->has('tier_id')) {
            $filters['tier_id'] = ['value' => request()->get('tier_id')];
        }

        if (!empty($filters)) {
            $this->tableFilters = $filters;
        }
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();

        // Применяем дополнительные фильтры из URL если они есть
        if (request()->has('case_id')) {
            $query->where('case_id', request()->get('case_id'));
        }

        if (request()->has('tier_id')) {
            $query->where('tier_id', request()->get('tier_id'));
        }

        return $query;
    }

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
        // Пробуем получить из разных источников
        $caseId = $this->tableFilters['case_id']['value'] ??
            request()->input('tableFilters.case_id.value') ??
            request()->get('case_id');

        if($caseId){
            $case = \App\Models\CaseModel::find($caseId);
            if ($case) {
                $description = '<div class="text-gray-500 dark:text-gray-400" style="margin-bottom: 5px;">Кейс: ' . $case->name . '</div>';
            }
        }

        $tierId = $this->tableFilters['tier_id']['value'] ??
            request()->input('tableFilters.tier_id.value') ??
            request()->get('tier_id');

        if($tierId){
            $tier = \App\Models\CaseTier::find($tierId);
            if ($tier) {
                $description .= '<div class="text-gray-500 dark:text-gray-400" style="margin-bottom: 5px;">Уровень: ' . $tier->name . ' (цена: ' . number_format($tier->price, 2) . ' ₽ • Вероятность: ' . $tier->probability . '%)</div>';
            }
        }
        return new HtmlString($description);
    }

    public function getHeading(): string
    {
        return 'Список предметов';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }



    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Добавить')
                ->modalHeading('Добавить предметы')
                ->createAnother(false)
                ->modalSubmitActionLabel('Добавить')
                ->modalCancelActionLabel('Отмена')
                ->fillForm(function () {
                    $caseId = $this->tableFilters['case_id']['value'] ??
                        request()->input('tableFilters.case_id.value') ??
                        request()->get('case_id');

                    $tierId = $this->tableFilters['tier_id']['value'] ??
                        request()->input('tableFilters.tier_id.value') ??
                        request()->get('tier_id');

                    return [
                        'case_id' => $caseId,
                        'tier_id' => $tierId,
                    ];
                })
                ->action(function (array $data) {
                    $caseId = $data['case_id'];
                    $tierId = $data['tier_id'];
                    $inventoryItemIds = $data['inventory_item_ids'];

                    foreach ($inventoryItemIds as $inventoryItemId) {
                        \App\Models\CaseItem::create([
                            'case_id' => $caseId,
                            'tier_id' => $tierId,
                            'inventory_item_id' => $inventoryItemId,
                        ]);
                    }
                })
                ->after(function () {
                    // Обновляем список после добавления
                    $this->dispatch('refreshTable');
                }),
        ];
    }
}
