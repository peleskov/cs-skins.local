<?php

namespace App\Filament\Resources\TranslationResource\Pages;

use App\Filament\Resources\TranslationResource;
use App\Models\Translation;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ListTranslations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TranslationResource::class;

    protected static string $view = 'filament.resources.translation-resource.pages.list-translations';

    protected ?string $heading = 'Переводы';

    public string $search = '';
    public string $filterGroup = '';
    public array $translations = [];
    public array $editingCell = [];
    public string $editingValue = '';
    public int $currentPage = 1;
    public int $perPage = 25;

    public function mount(): void
    {
        $this->loadTranslations();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Переводы';
    }

    protected function loadTranslations(): void
    {
        $this->translations = Translation::getAllTranslationsForTable();
    }

    public function getFilteredTranslations(): array
    {
        $filtered = $this->translations;

        // Фильтр по группе
        if ($this->filterGroup) {
            $filtered = array_filter($filtered, fn($item) => $item['group'] === $this->filterGroup);
        }

        // Поиск
        if ($this->search) {
            $search = mb_strtolower($this->search);
            $filtered = array_filter($filtered, function($item) use ($search) {
                // Поиск по ключу и группе
                if (str_contains(mb_strtolower($item['key']), $search) ||
                    str_contains(mb_strtolower($item['group']), $search)) {
                    return true;
                }
                // Поиск по значениям переводов
                foreach ($item as $key => $value) {
                    if (!in_array($key, ['id', 'group', 'key']) && is_string($value)) {
                        if (str_contains(mb_strtolower($value), $search)) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }

        return array_values($filtered);
    }

    public function getPaginatedTranslations()
    {
        $filtered = $this->getFilteredTranslations();
        $offset = ($this->currentPage - 1) * $this->perPage;

        return array_slice($filtered, $offset, $this->perPage);
    }

    public function getTotalPages(): int
    {
        $total = count($this->getFilteredTranslations());
        return (int) ceil($total / $this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->currentPage = 1;
    }

    public function updatedFilterGroup(): void
    {
        $this->currentPage = 1;
    }

    public function previousPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function nextPage(): void
    {
        if ($this->currentPage < $this->getTotalPages()) {
            $this->currentPage++;
        }
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
    }

    public function startEdit($id, $locale): void
    {
        // Находим текущее значение для редактирования
        foreach ($this->translations as $item) {
            if ($item['id'] === $id) {
                $this->editingValue = $item[$locale] ?? '';
                break;
            }
        }

        $this->editingCell = ['id' => $id, 'locale' => $locale];
    }

    public function saveTranslation(): void
    {
        if (empty($this->editingCell)) {
            return;
        }

        $id = $this->editingCell['id'];
        $locale = $this->editingCell['locale'];
        $value = $this->editingValue;

        $parts = explode('.', $id);
        $group = $parts[0];
        $key = implode('.', array_slice($parts, 1));

        $translation = new Translation($locale, $group);
        $translation->setTranslation($key, $value);
        $translation->save();

        // Обновляем в локальном массиве
        foreach ($this->translations as &$item) {
            if ($item['id'] === $id) {
                $item[$locale] = $value;
                break;
            }
        }

        $this->editingCell = [];
        $this->editingValue = '';

        Notification::make()
            ->title('Перевод сохранен')
            ->success()
            ->send();
    }

    public function cancelEdit(): void
    {
        $this->editingCell = [];
        $this->editingValue = '';
    }

    public function getGroups(): array
    {
        $groups = Translation::getAvailableGroups('en');
        return array_combine($groups, $groups);
    }

    public function getLocales(): array
    {
        return Translation::getAvailableLocales();
    }

    public function addTranslation($data): void
    {
        $group = $data['group'];
        $key = $data['key'];
        $locales = $this->getLocales();

        foreach ($locales as $locale) {
            if (!empty($data[$locale])) {
                $translation = new Translation($locale, $group);
                $translation->setTranslation($key, $data[$locale]);
                $translation->save();
            }
        }

        $this->loadTranslations();

        Notification::make()
            ->title('Перевод добавлен')
            ->success()
            ->send();
    }
}