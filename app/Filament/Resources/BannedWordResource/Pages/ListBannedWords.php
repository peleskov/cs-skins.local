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
                    $path = storage_path('app/public/' . $data['csv_file']);

                    if (!file_exists($path)) {
                        Notification::make()->title('Файл не найден')->danger()->send();
                        return;
                    }

                    $content = file_get_contents($path);
                    // Разделители: перенос строки, запятая, точка с запятой
                    $words = preg_split('/[\r\n,;]+/', $content);
                    $words = array_filter(array_map('trim', $words));
                    $words = array_unique(array_map('mb_strtolower', $words));

                    $existing = BannedWord::pluck('word')->map(fn ($w) => mb_strtolower($w))->toArray();
                    $new = array_diff($words, $existing);

                    $inserted = 0;
                    foreach ($new as $word) {
                        if (mb_strlen($word) > 0 && mb_strlen($word) <= 100) {
                            BannedWord::create(['word' => $word]);
                            $inserted++;
                        }
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
