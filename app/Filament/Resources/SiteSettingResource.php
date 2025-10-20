<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SiteSettingResource\Pages\ListSiteSettings;
use App\Filament\Resources\SiteSettingResource\Pages\CreateSiteSetting;
use App\Filament\Resources\SiteSettingResource\Pages\EditSiteSetting;
use App\Filament\Resources\SiteSettingResource\Pages;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Настройки сайта';

    protected static ?string $modelLabel = 'Настройка';

    protected static ?string $pluralModelLabel = 'Настройки сайта';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Ключ')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Уникальный идентификатор настройки')
                    ->columnSpan(1),

                Select::make('type')
                    ->label('Тип')
                    ->required()
                    ->options([
                        SiteSetting::TYPE_STRING => 'Строка',
                        SiteSetting::TYPE_NUMBER => 'Число',
                        SiteSetting::TYPE_BOOLEAN => 'Булево (Да/Нет)',
                        SiteSetting::TYPE_JSON => 'JSON',
                    ])
                    ->reactive()
                    ->columnSpan(1),

                TextInput::make('description')
                    ->label('Описание')
                    ->maxLength(255)
                    ->helperText('Описание для чего нужна эта настройка')
                    ->columnSpanFull(),

                Group::make([
                    Textarea::make('value')
                        ->label('Значение')
                        ->required()
                        ->visible(fn ($get) => in_array($get('type'), [SiteSetting::TYPE_STRING, SiteSetting::TYPE_JSON]))
                        ->rows(4)
                        ->helperText('Введите значение'),

                    TextInput::make('value')
                        ->label('Значение')
                        ->required()
                        ->visible(fn ($get) => $get('type') === SiteSetting::TYPE_NUMBER)
                        ->numeric()
                        ->helperText('Введите число'),

                    Toggle::make('boolean_value')
                        ->label('Значение')
                        ->visible(fn ($get) => $get('type') === SiteSetting::TYPE_BOOLEAN)
                        ->helperText('Включить/выключить настройку'),
                ])
                ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('description')
                    ->label('Описание')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        SiteSetting::TYPE_STRING => 'Строка',
                        SiteSetting::TYPE_NUMBER => 'Число',
                        SiteSetting::TYPE_BOOLEAN => 'Булево',
                        SiteSetting::TYPE_JSON => 'JSON',
                        default => $state
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        SiteSetting::TYPE_STRING => 'primary',
                        SiteSetting::TYPE_NUMBER => 'success',
                        SiteSetting::TYPE_BOOLEAN => 'warning',
                        SiteSetting::TYPE_JSON => 'danger',
                        default => 'gray'
                    }),

                TextColumn::make('value')
                    ->label('Значение')
                    ->formatStateUsing(function ($state, $record) {
                        return match($record->type) {
                            SiteSetting::TYPE_BOOLEAN => in_array($state, ['1', 1, true, 'true'], true) ? 'Да' : 'Нет',
                            SiteSetting::TYPE_JSON => json_encode(json_decode($state), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                            default => $state
                        };
                    })
                    ->limit(50)
                    ->copyable(),

                TextColumn::make('updated_at')
                    ->label('Изменено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        SiteSetting::TYPE_STRING => 'Строка',
                        SiteSetting::TYPE_NUMBER => 'Число',
                        SiteSetting::TYPE_BOOLEAN => 'Булево',
                        SiteSetting::TYPE_JSON => 'JSON',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
            ])
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteSettings::route('/'),
            'create' => CreateSiteSetting::route('/create'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}