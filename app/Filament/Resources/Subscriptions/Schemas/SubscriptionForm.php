<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Models\Client;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Клиент')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('subscription_plan_id')
                    ->label('Тариф')
                    ->relationship('plan', 'name')
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $plan = \App\Models\SubscriptionPlan::find($state);
                            if ($plan) {
                                $set('started_at', now());
                                $set('expires_at', now()->addDays($plan->duration_days));
                            }
                        }
                    }),
                DateTimePicker::make('started_at')
                    ->label('Начало')
                    ->required()
                    ->default(now()),
                DateTimePicker::make('expires_at')
                    ->label('Истекает')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Активна')
                    ->default(true),
            ]);
    }
}
