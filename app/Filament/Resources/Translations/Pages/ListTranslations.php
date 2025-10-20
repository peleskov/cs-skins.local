<?php

namespace App\Filament\Resources\Translations\Pages;

use App\Filament\Resources\Translations\TranslationResource;
use App\Models\Translation;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListTranslations extends ListRecords
{
    protected static string $resource = TranslationResource::class;

    public function mount(): void
    {
        parent::mount();

        // Синхронизируем файлы переводов с БД при каждом открытии страницы
        $this->syncTranslationsFromFiles();
    }

    protected function syncTranslationsFromFiles(): void
    {
        try {
            $synced = Translation::syncFromFiles();

            if ($synced > 0) {
                Notification::make()
                    ->title('Переводы синхронизированы')
                    ->body("Синхронизировано {$synced} переводов из файлов")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка синхронизации')
                ->body('Не удалось синхронизировать переводы: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Синхронизировать')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->syncTranslationsFromFiles();
                })
                ->tooltip('Синхронизировать переводы из файлов в БД'),

            CreateAction::make()
                ->label('Добавить перевод'),
        ];
    }
}
