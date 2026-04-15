<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('1 неделя'),
                TextInput::make('price')
                    ->label('Цена')
                    ->required()
                    ->numeric()
                    ->prefix('₽')
                    ->step(1),
                TextInput::make('duration_days')
                    ->label('Длительность (дней)')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Toggle::make('is_trial')
                    ->label('Триал')
                    ->helperText('Триал доступен один раз на аккаунт'),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
