<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RiggedUsersWidget extends BaseWidget
{
    protected static ?string $heading = 'Подкрученные клиенты';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Пользователь')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rigging_until')
                    ->label('Подкрутка до')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('case_opens_count')
                    ->label('Открытий')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('total_deposits')
                    ->label('Пополнений')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('upgrades_count')
                    ->label('Апгрейдов')
                    ->sortable()
                    ->default(0),
            ])
            ->defaultSort('rigging_until', 'desc')
            ->paginated([5, 10, 25]);
    }

    protected function getQuery()
    {
        return Client::query()
            ->rigged()
            ->select('clients.*')
            ->selectRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.client_id = clients.id) as case_opens_count')
            ->selectRaw('(SELECT COALESCE(SUM(price_paid), 0) FROM case_opens WHERE case_opens.client_id = clients.id) as total_spent')
            ->selectRaw("(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.client_id = clients.id AND payments.status = 'paid') as total_deposits")
            ->selectRaw('(SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id) as upgrades_count');
    }
}
