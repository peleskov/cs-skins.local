<?php

namespace App\Filament\Resources\Promocodes\Schemas;

use App\Models\Promocode;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PromocodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('code')
                            ->label('Код промокода')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->placeholder('SUMMER25')
                            ->regex('/^[A-Za-z0-9_-]+$/')
                            ->validationMessages([
                                'regex' => 'Только английские буквы, цифры, _ и -',
                            ])
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated()
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->hidden(fn ($record) => $record !== null)
                                    ->action(function ($set, $get) {
                                        $prefix = $get('code') ?? '';
                                        $set('code', $prefix.Str::upper(Str::random(6)));
                                    })
                            ),

                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Тип')
                                ->options([
                                    Promocode::TYPE_PERCENT => 'Процент от пополнения',
                                    Promocode::TYPE_FIXED => 'Фиксированная сумма',
                                ])
                                ->required()
                                ->default(Promocode::TYPE_PERCENT)
                                ->reactive(),

                            TextInput::make('value')
                                ->label(fn (callable $get) => $get('type') === Promocode::TYPE_PERCENT ? 'Процент (%)' : 'Сумма (₽)')
                                ->required()
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0.01),
                        ]),

                        TextInput::make('min_deposit')
                            ->label('Минимальная сумма пополнения')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->suffix('₽')
                            ->helperText('0 = без ограничения'),
                    ]),

                Section::make('Лимиты использования')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('max_uses')
                                ->label('Общий лимит использований')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('Без ограничения')
                                ->helperText('Пусто = безлимитно'),

                            TextInput::make('max_uses_per_user')
                                ->label('Лимит на пользователя')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->default(1),
                        ]),

                        TextInput::make('used_count')
                            ->label('Использовано раз')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Период действия')
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Начало действия')
                                ->helperText('Пусто = сразу активен'),

                            DateTimePicker::make('expires_at')
                                ->label('Окончание действия')
                                ->helperText('Пусто = бессрочный'),
                        ]),
                    ]),

                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                Section::make('LosReferidos')
                    ->schema([
                        TextInput::make('partner_id')
                            ->label('ID партнёра')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('lr_offer_id')
                            ->label('ID оффера в LR')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->visible(fn ($record) => $record?->partner_id !== null),
            ]);
    }
}
