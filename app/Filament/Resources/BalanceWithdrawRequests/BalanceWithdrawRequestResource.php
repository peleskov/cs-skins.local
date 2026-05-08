<?php

namespace App\Filament\Resources\BalanceWithdrawRequests;

use App\Filament\Resources\BalanceWithdrawRequests\Pages\ManageBalanceWithdrawRequests;
use App\Models\BalanceWithdrawRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class BalanceWithdrawRequestResource extends Resource
{
    protected static ?string $model = BalanceWithdrawRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Заявки на вывод (баланс)';

    protected static ?string $modelLabel = 'Заявка на вывод';

    protected static ?string $pluralModelLabel = 'Заявки на вывод';

    protected static string|\UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BalanceWithdrawRequest::where('status', BalanceWithdrawRequest::STATUS_PENDING)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Пользователь')
                    ->description(fn ($record) => $record->client?->email)
                    ->url(fn ($record) => route('filament.admin.resources.clients.edit', ['record' => $record->client_id]))
                    ->openUrlInNewTab(),

                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('withdrawn_24h_snapshot')
                    ->label('Заявок за 24ч (на момент)')
                    ->money('RUB')
                    ->alignEnd(),

                TextColumn::make('withdrawn_1h_snapshot')
                    ->label('Заявок за 1ч (на момент)')
                    ->money('RUB')
                    ->alignEnd(),

                IconColumn::make('limit_exceeded')
                    ->label('Превышен лимит')
                    ->boolean()
                    ->trueColor('danger'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Ожидает',
                        'approved' => 'Одобрена',
                        'rejected' => 'Отклонена',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),

                TextColumn::make('processedBy.name')
                    ->label('Обработал')
                    ->placeholder('-'),

                TextColumn::make('processed_at')
                    ->label('Обработана')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'approved' => 'Одобрена',
                        'rejected' => 'Отклонена',
                    ])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === BalanceWithdrawRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => BalanceWithdrawRequest::STATUS_APPROVED,
                                'processed_by' => auth()->id(),
                                'processed_at' => now(),
                            ]);

                            \App\Models\Transaction::where('client_id', $record->client_id)
                                ->where('type', \App\Models\Transaction::TYPE_WITHDRAWAL)
                                ->whereJsonContains('metadata->balance_withdraw_request_id', $record->id)
                                ->update(['status' => 'completed']);
                        });

                        Notification::make()
                            ->title('Заявка одобрена')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === BalanceWithdrawRequest::STATUS_PENDING)
                    ->schema([
                        Textarea::make('comment')
                            ->label('Комментарий (для админа)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // Возвращаем сумму на баланс
                            $record->client->increment('balance', $record->amount);

                            $until = now()->addHours(48);
                            $userReason = 'Ваш вывод временно приостановлен на 48 часов за подозрительные действия. Разблокировка будет доступна '.$until->format('d.m.Y H:i').'.';

                            $record->client->update([
                                'balance_blocked_until' => $until,
                                'balance_block_reason_admin' => 'Авто-блок после отклонения заявки на вывод #'.$record->id,
                                'balance_block_reason_user' => $userReason,
                            ]);

                            $record->update([
                                'status' => BalanceWithdrawRequest::STATUS_REJECTED,
                                'processed_by' => auth()->id(),
                                'processed_at' => now(),
                                'admin_comment' => $data['comment'] ?? null,
                            ]);

                            // Помечаем pending-транзакцию как cancelled и пишем возврат
                            \App\Models\Transaction::where('client_id', $record->client_id)
                                ->where('type', \App\Models\Transaction::TYPE_WITHDRAWAL)
                                ->whereJsonContains('metadata->balance_withdraw_request_id', $record->id)
                                ->update(['status' => 'cancelled']);

                            \App\Models\Transaction::create([
                                'client_id' => $record->client_id,
                                'type' => \App\Models\Transaction::TYPE_REFUND,
                                'amount' => $record->amount,
                                'status' => 'completed',
                                'description' => 'Возврат по отклонённой заявке на вывод #'.$record->id,
                                'metadata' => ['balance_withdraw_request_id' => $record->id],
                            ]);
                        });

                        Notification::make()
                            ->title('Заявка отклонена, сумма возвращена, баланс заблокирован на 48 часов')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBalanceWithdrawRequests::route('/'),
        ];
    }
}
