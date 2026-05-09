<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Exception;
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
                    
                TextInput::make('telegram_id')
                    ->label('Telegram ID')
                    ->maxLength(255)
                    ->nullable(),

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

                Textarea::make('admin_comment')
                    ->label('Комментарий админа')
                    ->helperText('Внутренние заметки по клиенту, не показываются пользователю')
                    ->rows(4)
                    ->columnSpanFull()
                    ->nullable(),

                \Filament\Schemas\Components\Section::make('Подкрутка')
                    ->description('Особый режим открытия кейсов — клиент исключается из основной экономики')
                    ->schema([
                        Forms\Components\Toggle::make('rigging_enabled')
                            ->label('Включить подкрутку')
                            ->live(),
                        Forms\Components\DateTimePicker::make('rigging_until')
                            ->label('Действует до')
                            ->visible(fn ($get) => $get('rigging_enabled'))
                            ->required(fn ($get) => $get('rigging_enabled'))
                            ->minDate(now()),
                        Forms\Components\Repeater::make('riggingPresets')
                            ->label('Пресеты')
                            ->relationship('riggingPresets')
                            ->visible(fn ($get) => $get('rigging_enabled'))
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('price_percent')
                                    ->label('% от цены кейса')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->maxValue(1000)
                                    ->step(0.01)
                                    ->suffix('%'),
                                TextInput::make('chance_percent')
                                    ->label('% шанса')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->live(debounce: 500),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => isset($state['name'])
                                ? $state['name'].' — '.($state['price_percent'] ?? '?').'% / '.($state['chance_percent'] ?? '?').'%'
                                : null)
                            ->helperText(function ($get) {
                                $sum = collect($get('riggingPresets') ?? [])->sum(fn ($p) => (float) ($p['chance_percent'] ?? 0));
                                $rounded = round($sum, 2);
                                $color = abs($rounded - 100) < 0.001 ? 'success' : 'danger';
                                return new \Illuminate\Support\HtmlString(
                                    'Сумма шансов: <span class="fi-color-'.$color.'" style="font-weight:600">'.$rounded.'%</span> (должна быть ровно 100%)'
                                );
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $sum = collect($value ?? [])->sum(fn ($p) => (float) ($p['chance_percent'] ?? 0));
                                        if (abs(round($sum, 2) - 100) > 0.001) {
                                            $fail('Сумма % шанса всех пресетов должна быть ровно 100% (сейчас '.round($sum, 2).'%).');
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->collapsed(fn ($record) => ! $record?->rigging_enabled),

                \Filament\Schemas\Components\Section::make('Партнёр (LosReferidos)')
                    ->schema([
                        Forms\Components\Placeholder::make('partner_email')
                            ->label('Email партнёра')
                            ->content(fn (?Client $record): string => $record?->referral?->partner?->email ?? 'Нет'),
                        Forms\Components\Placeholder::make('partner_link_id')
                            ->label('Link ID')
                            ->content(fn (?Client $record): string => $record?->referral?->link_id ?? '-'),
                        Forms\Components\Placeholder::make('referral_external_id')
                            ->label('External ID')
                            ->content(fn (?Client $record): string => $record?->referral?->id ? (string) $record->referral->id : '-'),
                        Forms\Components\Placeholder::make('referral_status')
                            ->label('Статус')
                            ->content(fn (?Client $record): string => $record?->referral ? ($record->referral->is_active ? 'Активен' : 'Неактивен') : 'Нет'),
                    ])
                    ->visibleOn('edit')
                    ->collapsed(),
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
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\BonusTransactionsRelationManager::class,
            RelationManagers\PromocodeActivationsRelationManager::class,
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

    /**
     * Создать action блокировки/разблокировки одного из аспектов клиента.
     * $kind: 'withdraw' | 'purchases' | 'balance'
     */
    public static function makeBlockAction(string $kind, string $titleShort, string $icon): Action
    {
        $untilCol = $kind.'_blocked_until';
        $reasonAdmin = $kind.'_block_reason_admin';
        $reasonUser = $kind.'_block_reason_user';

        return Action::make('block_'.$kind)
            ->label(function ($record) use ($untilCol, $titleShort) {
                $until = $record?->{$untilCol};
                return $until && $until->isFuture()
                    ? 'Разблокировать: '.$titleShort
                    : 'Заблокировать: '.$titleShort;
            })
            ->icon(fn ($record) => $record->{$untilCol} && $record->{$untilCol}->isFuture()
                ? 'heroicon-o-lock-closed'
                : 'heroicon-o-lock-open')
            ->color(fn ($record) => $record->{$untilCol} && $record->{$untilCol}->isFuture()
                ? 'danger'
                : 'success')
            ->modalHeading(fn ($record) => $record->{$untilCol} && $record->{$untilCol}->isFuture()
                ? 'Разблокировать: '.$titleShort
                : 'Заблокировать: '.$titleShort)
            ->modalSubmitActionLabel(fn ($record) => $record->{$untilCol} && $record->{$untilCol}->isFuture()
                ? 'Разблокировать'
                : 'Заблокировать')
            ->fillForm(fn ($record) => [
                'until' => $record->{$untilCol},
                'reason_admin' => $record->{$reasonAdmin},
                'reason_user' => $record->{$reasonUser},
                'permanent' => $record->{$untilCol} && $record->{$untilCol}->year >= now()->year + 50,
            ])
            ->schema(fn ($record) => $record->{$untilCol} && $record->{$untilCol}->isFuture() ? [
                \Filament\Forms\Components\DateTimePicker::make('until')
                    ->label('Действует до')
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('reason_admin')
                    ->label('Причина (для админа, не видна клиенту)')
                    ->rows(2)
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('reason_user')
                    ->label('Причина (показывается клиенту)')
                    ->rows(2)
                    ->disabled()
                    ->dehydrated(false),
            ] : [
                \Filament\Forms\Components\DateTimePicker::make('until')
                    ->label('Действует до')
                    ->required(fn ($get) => ! $get('permanent'))
                    ->minDate(now())
                    ->visible(fn ($get) => ! $get('permanent')),
                \Filament\Forms\Components\Toggle::make('permanent')
                    ->label('Бессрочно')
                    ->live(),
                Textarea::make('reason_admin')
                    ->label('Причина (для админа, не видна клиенту)')
                    ->rows(2),
                Textarea::make('reason_user')
                    ->label('Причина (показывается клиенту)')
                    ->rows(2)
                    ->required(),
            ])
            ->action(function ($record, array $data) use ($untilCol, $reasonAdmin, $reasonUser) {
                if ($record->{$untilCol} && $record->{$untilCol}->isFuture()) {
                    // Разблокировка
                    $record->update([
                        $untilCol => null,
                        $reasonAdmin => null,
                        $reasonUser => null,
                    ]);
                } else {
                    // Блокировка
                    $until = ($data['permanent'] ?? false) ? now()->addYears(100) : $data['until'];
                    $record->update([
                        $untilCol => $until,
                        $reasonAdmin => $data['reason_admin'] ?? null,
                        $reasonUser => $data['reason_user'] ?? null,
                    ]);
                }
            });
    }
}
