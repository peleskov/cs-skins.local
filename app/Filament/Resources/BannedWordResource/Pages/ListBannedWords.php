<?php

namespace App\Filament\Resources\BannedWordResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use App\Filament\Resources\BannedWordResource;
use App\Models\BannedWord;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListBannedWords extends ListRecords
{
    protected static string $resource = BannedWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_csv')
                ->label('Импорт CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('CSV файл')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('Файл с запрещёнными словами — по одному слову на строку или через запятую/точку с запятой'),
                ])
                ->action(function (array $data) {
                    @set_time_limit(300);
                    $path = storage_path('app/public/' . $data['csv_file']);

                    if (!file_exists($path)) {
                        Notification::make()->title('Файл не найден')->danger()->send();
                        return;
                    }

                    $content = file_get_contents($path);
                    // Убираем UTF-8 BOM, если он есть в начале файла
                    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                    // Разделители: перенос строки, запятая, точка с запятой
                    $words = preg_split('/[\r\n,;]+/', $content);
                    // Чистим невидимые символы (BOM, zero-width) и пробелы
                    $words = array_map(fn ($w) => trim(preg_replace('/[\x{FEFF}\x{200B}-\x{200D}]/u', '', $w)), $words);
                    $words = array_unique(array_map('mb_strtolower', array_filter($words)));
                    // Отфильтровываем по длине
                    $words = array_filter($words, fn ($w) => mb_strlen($w) > 0 && mb_strlen($w) <= 100);

                    $existing = BannedWord::pluck('word')->map(fn ($w) => mb_strtolower($w))->all();
                    $new = array_values(array_diff($words, $existing));

                    $now = now();
                    $inserted = 0;
                    foreach (array_chunk($new, 500) as $chunk) {
                        $rows = array_map(fn ($w) => ['word' => $w, 'created_at' => $now, 'updated_at' => $now], $chunk);
                        $inserted += BannedWord::insertOrIgnore($rows);
                    }

                    BannedWord::clearCache();
                    @unlink($path);

                    Notification::make()
                        ->title("Импортировано: {$inserted} слов")
                        ->body('Пропущено дубликатов: ' . (count($words) - $inserted))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
