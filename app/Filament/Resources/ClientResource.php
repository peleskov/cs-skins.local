<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\Transaction;
use App\Models\BonusTransaction;
use App\Services\BonusBalanceService;
use Illuminate\Support\Facades\Auth;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Клиенты';
    
    protected static ?string $modelLabel = 'Клиент';
    
    protected static ?string $pluralModelLabel = 'Клиенты';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Пользователи';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),
                    
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                    
                TextInput::make('steam_id')
                    ->label('Steam ID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                TextInput::make('steam_avatar')
                    ->label('Steam Avatar URL')
                    ->url()
                    ->maxLength(255),
                    
                TextInput::make('steam_trade_url')
                    ->label('Steam Trade URL')
                    ->url()
                    ->maxLength(500),
                    
                TextInput::make('balance')
                    ->label('Баланс')
                    ->numeric()
                    ->default(0)
                    ->step(0.01)
                    ->suffix('₽')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('bonus_balance')
                    ->label('Бонусный баланс')
                    ->numeric()
                    ->default(0)
                    ->step(0.01)
                    ->suffix('₽')
                    ->disabled()
                    ->dehydrated(false),
                    
                Toggle::make('is_verified')
                    ->label('Верифицирован'),
                    
                Toggle::make('is_bot')
                    ->label('Бот'),
                    
                Select::make('locale')
                    ->label('Язык')
                    ->options([
                        'ru' => 'Русский',
                        'en' => 'English',
                    ])
                    ->default('ru'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('steam_avatar')
                    ->label('Аватар')
                    ->circular()
                    ->size(50),
                    
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('steam_id')
                    ->label('Steam ID')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('balance')
                    ->label('Баланс')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('bonus_balance')
                    ->label('Бонусный баланс')
                    ->money('RUB')
                    ->sortable()
                    ->color('success'),
                    
                IconColumn::make('is_verified')
                    ->label('Верифицирован')
                    ->boolean(),

                IconColumn::make('is_bot')
                    ->label('Бот')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_verified')
                    ->label('Верификация'),
                    
                TernaryFilter::make('is_bot')
                    ->label('Боты'),
                    
                SelectFilter::make('locale')
                    ->label('Язык')
                    ->options([
                        'ru' => 'Русский',
                        'en' => 'English',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Изменить'),
                Action::make('topup_balance')
                    ->label('')
                    ->tooltip('Пополнить баланс')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Сумма')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('₽'),
                        Textarea::make('description')
                            ->label('Описание')
                            ->default('Подарок от администрации')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Пополняем баланс клиента
                            $record->increment('balance', $data['amount']);

                            // Создаем транзакцию
                            Transaction::create([
                                'client_id' => $record->id,
                                'type' => Transaction::TYPE_DEPOSIT,
                                'amount' => $data['amount'],
                                'status' => Transaction::STATUS_COMPLETED,
                                'description' => $data['description'],
                                'metadata' => [
                                    'admin_topup' => true,
                                    'admin_id' => Auth::id(),
                                ]
                            ]);

                            Notification::make()
                                ->title('Баланс пополнен успешно')
                                ->body("Баланс клиента {$record->name} пополнен на {$data['amount']} ₽")
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Ошибка при пополнении баланса')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Пополнить баланс клиента')
                    ->modalDescription(fn ($record) => "Вы собираетесь пополнить баланс клиента {$record->name}"),
                Action::make('withdraw_balance')
                    ->label('')
                    ->tooltip('Снять с баланса')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('danger')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Сумма')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('₽'),
                        Textarea::make('description')
                            ->label('Описание')
                            ->default('Снятие с баланса администратором')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Проверяем достаточно ли средств на балансе
                            if ($record->balance < $data['amount']) {
                                Notification::make()
                                    ->title('Недостаточно средств')
                                    ->body("На балансе клиента {$record->name} недостаточно средств. Текущий баланс: {$record->balance} ₽")
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Списываем с баланса клиента
                            $record->decrement('balance', $data['amount']);

                            // Создаем транзакцию
                            Transaction::create([
                                'client_id' => $record->id,
                                'type' => Transaction::TYPE_WITHDRAWAL,
                                'amount' => $data['amount'],
                                'status' => Transaction::STATUS_COMPLETED,
                                'description' => $data['description'],
                                'metadata' => [
                                    'admin_withdrawal' => true,
                                    'admin_id' => Auth::id(),
                                ]
                            ]);

                            Notification::make()
                                ->title('Баланс списан успешно')
                                ->body("С баланса клиента {$record->name} списано {$data['amount']} ₽")
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Ошибка при списании с баланса')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Снять с баланса клиента')
                    ->modalDescription(fn ($record) => "Вы собираетесь списать средства с баланса клиента {$record->name}. Текущий баланс: {$record->balance} ₽"),
                Action::make('topup_bonus')
                    ->label('')
                    ->tooltip('Пополнить бонусный баланс')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Сумма')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('₽'),
                        Textarea::make('description')
                            ->label('Причина')
                            ->default('Начисление бонуса администратором')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $service = app(BonusBalanceService::class);
                            $service->credit(
                                $record,
                                $data['amount'],
                                $data['description']
                            );

                            Notification::make()
                                ->title('Бонус начислен')
                                ->body("Клиенту {$record->name} начислено {$data['amount']} ₽ бонусов")
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Ошибка при начислении бонуса')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Начислить бонус')
                    ->modalDescription(fn ($record) => "Начисление бонуса клиенту {$record->name}. Текущий бонусный баланс: {$record->bonus_balance} ₽"),
                Action::make('withdraw_bonus')
                    ->label('')
                    ->tooltip('Снять бонусы')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Сумма')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('₽'),
                        Textarea::make('description')
                            ->label('Причина')
                            ->default('Списание бонуса администратором')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            if ($record->bonus_balance < $data['amount']) {
                                Notification::make()
                                    ->title('Недостаточно бонусов')
                                    ->body("Бонусный баланс клиента: {$record->bonus_balance} ₽")
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $service = app(BonusBalanceService::class);
                            $service->debit(
                                $record,
                                $data['amount'],
                                $data['description']
                            );

                            Notification::make()
                                ->title('Бонус списан')
                                ->body("С клиента {$record->name} списано {$data['amount']} ₽ бонусов")
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Ошибка при списании бонуса')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Списать бонус')
                    ->modalDescription(fn ($record) => "Списание бонуса с клиента {$record->name}. Текущий бонусный баланс: {$record->bonus_balance} ₽"),
            ])
            ->recordActionsColumnLabel('Действия')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\BonusTransactionsRelationManager::class,
            RelationManagers\CaseInventoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
