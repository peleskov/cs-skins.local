<?php

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Filament\Resources\ClientResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static ?string $title = 'Рефералы';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('External ID')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->url(fn ($record) => ClientResource::getUrl('edit', ['record' => $record->client_id]))
                    ->color('primary'),
                TextColumn::make('client.steam_id')
                    ->label('Steam ID'),
                TextColumn::make('link_id')
                    ->label('Link ID'),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Привязан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
