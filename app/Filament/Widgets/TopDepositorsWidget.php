<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopDepositorsWidget extends BaseWidget
{
    protected static ?string $heading = 'Топ пополнений по клиентам';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Клиент')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deposits_count')
                    ->label('Платежей')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('deposits_total')
                    ->label('Сумма пополнений')
                    ->money('RUB')
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('last_deposit_at')
                    ->label('Последнее')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('deposits_total', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getQuery()
    {
        $paidStatus = Payment::STATUS_PAID;

        return Client::query()
            ->notRigged()
            ->select('clients.*')
            ->selectRaw("(SELECT COUNT(*) FROM payments WHERE payments.client_id = clients.id AND payments.status = '{$paidStatus}') as deposits_count")
            ->selectRaw("(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.client_id = clients.id AND payments.status = '{$paidStatus}') as deposits_total")
            ->selectRaw("(SELECT MAX(paid_at) FROM payments WHERE payments.client_id = clients.id AND payments.status = '{$paidStatus}') as last_deposit_at")
            ->havingRaw('deposits_count > 0');
    }
}
