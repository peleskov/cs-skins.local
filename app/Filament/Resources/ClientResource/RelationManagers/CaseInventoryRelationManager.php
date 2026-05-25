<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\CaseInventoryItem;
use App\Services\CaseInventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CaseInventoryRelationManager extends RelationManager
{
    protected static string $relationship = 'caseInventoryItems';

    protected static ?string $title = 'Инвентарь кейсов';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('virtualItem.image_url')
                    ->label('')
                    ->width(50)
                    ->height(40),

                TextColumn::make('virtualItem.name')
                    ->label('Предмет')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('source_type')
                    ->label('Источник')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CaseInventoryItem::getSourceTypes()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'case' => 'info',
                        'upgrade' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => CaseInventoryItem::getStatuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'sold' => 'gray',
                        'withdrawn' => 'info',
                        'upgraded' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(CaseInventoryItem::getStatuses()),

                SelectFilter::make('source_type')
                    ->label('Источник')
                    ->options(CaseInventoryItem::getSourceTypes()),
            ])
            ->recordActions([
                Action::make('sellForClient')
                    ->label('Продать')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (CaseInventoryItem $record) => $record->status === CaseInventoryItem::STATUS_AVAILABLE)
                    ->requiresConfirmation()
                    ->modalHeading('Продать предмет за клиента')
                    ->modalDescription(fn (CaseInventoryItem $record) => 'Предмет «' . $record->virtualItem->name . '» будет продан за ' . number_format((float) $record->price, 2, '.', ' ') . ' ₽. Сумма зачислится на баланс клиента, статус станет «Продан».')
                    ->modalSubmitActionLabel('Продать')
                    ->action(function (CaseInventoryItem $record): void {
                        try {
                            app(CaseInventoryService::class)->sellItems($record->client, [$record->id]);

                            Notification::make()
                                ->title('Предмет продан')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Не удалось продать')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
