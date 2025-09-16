<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
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
use Illuminate\Support\Facades\Auth;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Клиенты';
    
    protected static ?string $modelLabel = 'Клиент';
    
    protected static ?string $pluralModelLabel = 'Клиенты';
    
    protected static ?string $navigationGroup = 'Пользователи';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    
                IconColumn::make('is_verified')
                    ->label('Верифицирован')
                    ->boolean(),

                IconColumn::make('is_bot')
                    ->label('Бот')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Верификация'),
                    
                Tables\Filters\TernaryFilter::make('is_bot')
                    ->label('Боты'),
                    
                Tables\Filters\SelectFilter::make('locale')
                    ->label('Язык')
                    ->options([
                        'ru' => 'Русский',
                        'en' => 'English',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Изменить'),
                Tables\Actions\Action::make('topup_balance')
                    ->label('')
                    ->tooltip('Пополнить баланс')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
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

                        } catch (\Exception $e) {
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
                Tables\Actions\Action::make('withdraw_balance')
                    ->label('')
                    ->tooltip('Снять с баланса')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('danger')
                    ->form([
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

                        } catch (\Exception $e) {
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
            ])
            ->actionsColumnLabel('Действия')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
