<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Транзакции';

    protected static ?string $modelLabel = 'Транзакция';

    protected static ?string $pluralModelLabel = 'Транзакции';

    protected static string | \UnitEnum | null $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Клиент')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->required(),

                Select::make('order_id')
                    ->label('Заказ')
                    ->relationship('order', 'order_number')
                    ->searchable(),

                Select::make('type')
                    ->label('Тип транзакции')
                    ->required()
                    ->options([
                        Transaction::TYPE_PURCHASE => 'Покупка',
                        Transaction::TYPE_SALE => 'Продажа',
                        Transaction::TYPE_FEE => 'Комиссия',
                        Transaction::TYPE_REFUND => 'Возврат',
                        Transaction::TYPE_DEPOSIT => 'Пополнение',
                        Transaction::TYPE_WITHDRAWAL => 'Вывод средств',
                    ]),

                TextInput::make('amount')
                    ->label('Сумма')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->suffix('₽'),

                Select::make('status')
                    ->label('Статус')
                    ->required()
                    ->options([
                        Transaction::STATUS_PENDING => 'В обработке',
                        Transaction::STATUS_COMPLETED => 'Завершено',
                        Transaction::STATUS_FAILED => 'Ошибка',
                        Transaction::STATUS_ON_HOLD => 'На удержании',
                    ]),

                Textarea::make('description')
                    ->label('Описание')
                    ->maxLength(500),

                DateTimePicker::make('hold_until')
                    ->label('Удерживать до')
                    ->helperText('Дата и время, до которого транзакция заблокирована'),

                KeyValue::make('metadata')
                    ->label('Метаданные')
                    ->helperText('Дополнительные данные в формате JSON'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Заказ')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        Transaction::TYPE_PURCHASE => 'Покупка',
                        Transaction::TYPE_SALE => 'Продажа',
                        Transaction::TYPE_FEE => 'Комиссия',
                        Transaction::TYPE_REFUND => 'Возврат',
                        Transaction::TYPE_DEPOSIT => 'Пополнение',
                        Transaction::TYPE_WITHDRAWAL => 'Вывод средств',
                        default => $state
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        Transaction::TYPE_PURCHASE => 'danger',
                        Transaction::TYPE_SALE => 'success',
                        Transaction::TYPE_FEE => 'warning',
                        Transaction::TYPE_REFUND => 'info',
                        Transaction::TYPE_DEPOSIT => 'success',
                        Transaction::TYPE_WITHDRAWAL => 'warning',
                        default => 'gray'
                    }),

                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        Transaction::STATUS_PENDING => 'В обработке',
                        Transaction::STATUS_COMPLETED => 'Завершено',
                        Transaction::STATUS_FAILED => 'Ошибка',
                        Transaction::STATUS_ON_HOLD => 'На удержании',
                        default => $state
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        Transaction::STATUS_PENDING => 'warning',
                        Transaction::STATUS_COMPLETED => 'success',
                        Transaction::STATUS_FAILED => 'danger',
                        Transaction::STATUS_ON_HOLD => 'gray',
                        default => 'gray'
                    }),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('metadata')
                    ->label('ID платежа')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('metadata->order_id', 'like', "%{$search}%")),

                TextColumn::make('hold_until')
                    ->label('Удерживать до')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип транзакции')
                    ->options([
                        Transaction::TYPE_PURCHASE => 'Покупка',
                        Transaction::TYPE_SALE => 'Продажа',
                        Transaction::TYPE_FEE => 'Комиссия',
                        Transaction::TYPE_REFUND => 'Возврат',
                        Transaction::TYPE_DEPOSIT => 'Пополнение',
                        Transaction::TYPE_WITHDRAWAL => 'Вывод средств',
                    ]),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Transaction::STATUS_PENDING => 'В обработке',
                        Transaction::STATUS_COMPLETED => 'Завершено',
                        Transaction::STATUS_FAILED => 'Ошибка',
                        Transaction::STATUS_ON_HOLD => 'На удержании',
                    ]),

                SelectFilter::make('client_id')
                    ->label('Клиент')
                    ->relationship('client', 'name')
                    ->searchable(),

                Filter::make('on_hold')
                    ->label('На удержании')
                    ->query(fn (Builder $query): Builder => $query->where('hold_until', '>', now()))
                    ->toggle(),

                Filter::make('ready_for_release')
                    ->label('Готово к освобождению')
                    ->query(fn (Builder $query): Builder => $query->readyForRelease())
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
        ];
    }
}
