<?php

namespace App\Filament\Pages;

use App\Models\TranslationFile;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageTranslations extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationLabel = 'Переводы';
    protected static string|\UnitEnum|null $navigationGroup = 'Контент';
    protected string $view = 'filament.pages.manage-translations';

    public function getTitle(): string | Htmlable
    {
        return 'Переводы';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('group')
                    ->label('Группа')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                // Динамически создаем колонки для каждого языка
                ...collect(TranslationFile::getAvailableLocales())->map(function ($locale) {
                    return TextColumn::make($locale)
                        ->label(strtoupper($locale))
                        ->searchable()
                        ->formatStateUsing(function ($state) {
                            return empty($state) ? 'Пусто' : $state;
                        })
                        ->color(fn ($state) => empty($state) ? 'gray' : null)
                        ->italic(fn ($state) => empty($state));
                })->toArray(),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Группа')
                    ->options($this->getGroups()),
            ])
            ->defaultSort('group');
    }

    public function getTableQuery()
    {
        // Создаем коллекцию объектов из данных файлов
        $translations = TranslationFile::getAllTranslationsForTable();

        $records = collect($translations)->map(function ($item) {
            $obj = new \stdClass();
            $obj->id = $item['id'];
            $obj->group = $item['group'];
            $obj->key = $item['key'];

            // Добавляем значения для каждого языка
            foreach (TranslationFile::getAvailableLocales() as $locale) {
                $obj->{$locale} = $item[$locale] ?? '';
            }

            return $obj;
        });

        // Создаем фиктивный Builder для совместимости с Filament
        return new class($records) {
            private $records;

            public function __construct($records) {
                $this->records = $records;
            }

            public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null) {
                $page = $page ?: request()->get($pageName, 1);
                $offset = ($page - 1) * $perPage;

                return new LengthAwarePaginator(
                    $this->records->slice($offset, $perPage)->values(),
                    $this->records->count(),
                    $perPage,
                    $page,
                    [
                        'path' => request()->url(),
                        'pageName' => $pageName,
                    ]
                );
            }

            public function get() {
                return $this->records;
            }
        };
    }

    protected function getGroups(): array
    {
        $groups = TranslationFile::getAvailableGroups('en');
        return array_combine($groups, $groups);
    }

}