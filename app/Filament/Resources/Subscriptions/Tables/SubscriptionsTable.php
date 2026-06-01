<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use App\Services\SubscriptionService;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->client_id
                        ? route('filament.admin.resources.clients.edit', ['record' => $record->client_id])
                        : null),
                TextColumn::make('client.steam_id')
                    ->label('Steam ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('plan.name')
                    ->label('Тариф')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Истекает')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label('Осталось')
                    ->getStateUsing(fn ($record) => $record->isValid() ? $record->daysRemaining() . ' дн.' : 'Истекла')
                    ->badge()
                    ->color(fn ($record) => $record->isValid() ? 'success' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('client.pin_code')
                    ->label('Код-пароль')
                    ->getStateUsing(fn ($record) => !empty($record->client?->pin_code) ? 'Установлен' : 'Нет')
                    ->badge()
                    ->color(fn ($record) => !empty($record->client?->pin_code) ? 'warning' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Статус')
                    ->placeholder('Все')
                    ->trueLabel('Активные')
                    ->falseLabel('Неактивные'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetPinCode')
                    ->label('Сбросить код-пароль')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Сбросить код-пароль')
                    ->modalDescription('Код-пароль и кулдаун пользователя будут сброшены. Продолжить?')
                    ->visible(fn ($record) => !empty($record->client?->pin_code))
                    ->action(function ($record) {
                        $record->client->update(['pin_code' => null, 'pin_verified_at' => null]);
                        $settings = $record->settings ?? [];
                        unset($settings['pin_code_cooldown']);
                        $record->update(['settings' => $settings]);
                        SubscriptionService::log($record, 'pin_reset', 'Код-пароль и кулдаун сброшены администратором', performedBy: auth()->user()?->email);
                        Notification::make()
                            ->title('Код-пароль и кулдаун сброшены')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
