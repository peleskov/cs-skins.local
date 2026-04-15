<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'История действий';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Действие')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'extended' => 'info',
                        'expired', 'disabled' => 'danger',
                        'pin_reset' => 'warning',
                        'settings_changed', 'updated' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Описание')
                    ->wrap(),
                TextColumn::make('performed_by')
                    ->label('Кем')
                    ->default('Система')
                    ->badge()
                    ->color(fn ($state) => $state === 'Система' ? 'gray' : 'primary'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
