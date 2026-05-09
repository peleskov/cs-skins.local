<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Журнал действий';

    protected static ?string $modelLabel = 'Запись журнала';

    protected static ?string $pluralModelLabel = 'Журнал действий';

    protected static string|\UnitEnum|null $navigationGroup = 'Отчёты';

    protected static ?int $navigationSort = 99;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function canGloballySearch(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Кто')
                    ->placeholder('система'),

                TextColumn::make('event')
                    ->label('Действие')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('Объект')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),

                TextColumn::make('subject_id')
                    ->label('ID'),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(60),

                TextColumn::make('properties')
                    ->label('Изменения')
                    ->formatStateUsing(function ($state) {
                        $arr = is_string($state) ? json_decode($state, true) : (array) $state;
                        if (empty($arr)) {
                            return '-';
                        }
                        return collect($arr)->map(fn ($v, $k) => "$k: ".json_encode($v, JSON_UNESCAPED_UNICODE))->implode("\n");
                    })
                    ->wrap()
                    ->limit(200),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Действие')
                    ->options([
                        'created' => 'Создание',
                        'updated' => 'Обновление',
                        'deleted' => 'Удаление',
                    ]),

                SelectFilter::make('causer_id')
                    ->label('Кто')
                    ->options(fn () => \App\Models\User::pluck('name', 'id')),

                SelectFilter::make('subject_type')
                    ->label('Тип объекта')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($v, $k) => [$k => class_basename($k)])
                        ->all()),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('С'),
                        DatePicker::make('to')->label('По'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
