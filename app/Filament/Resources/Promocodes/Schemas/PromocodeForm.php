<?php

namespace App\Filament\Resources\Promocodes\Schemas;

use App\Models\BonusTransaction;
use App\Models\Promocode;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromocodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('code')
                            ->label('Код промокода')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->placeholder('SUMMER25')
                            ->regex('/^[A-Za-z0-9_-]+$/')
                            ->validationMessages([
                                'regex' => 'Только английские буквы, цифры, _ и -',
                            ])
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated()
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->hidden(fn ($record) => $record !== null)
                                    ->action(function ($set, $get) {
                                        $prefix = $get('code') ?? '';
                                        $set('code', $prefix.Str::upper(Str::random(6)));
                                    })
                            ),

                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Тип')
                                ->options([
                                    Promocode::TYPE_PERCENT => 'Процент от пополнения',
                                    Promocode::TYPE_FIXED => 'Фиксированная сумма',
                                ])
                                ->required()
                                ->default(Promocode::TYPE_PERCENT)
                                ->reactive(),

                            TextInput::make('value')
                                ->label(fn (callable $get) => $get('type') === Promocode::TYPE_PERCENT ? 'Процент (%)' : 'Сумма (₽)')
                                ->required()
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0.01),
                        ]),

                        TextInput::make('min_deposit')
                            ->label('Минимальная сумма пополнения')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->suffix('₽')
                            ->helperText('0 = без ограничения'),
                    ]),

                Section::make('Лимиты использования')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('max_uses')
                                ->label('Общий лимит использований')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('Без ограничения')
                                ->helperText('Пусто = безлимитно'),

                            TextInput::make('max_uses_per_user')
                                ->label('Лимит на пользователя')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->default(1),
                        ]),

                        TextInput::make('used_count')
                            ->label('Использовано раз')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Период действия')
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Начало действия')
                                ->helperText('Пусто = сразу активен'),

                            DateTimePicker::make('expires_at')
                                ->label('Окончание действия')
                                ->helperText('Пусто = бессрочный'),
                        ]),
                    ]),

                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                Section::make('Статистика активаций')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('stats_total_deposits')
                                ->label('Общая сумма пополнений')
                                ->content(fn ($record) => number_format(self::stats($record)['total_deposits'], 2, '.', ' ').' ₽'),

                            Placeholder::make('stats_total_bonuses')
                                ->label('Общая сумма выданных бонусов')
                                ->content(fn ($record) => number_format(self::stats($record)['total_bonuses'], 2, '.', ' ').' ₽'),

                            Placeholder::make('stats_total_activations')
                                ->label('Кол-во активаций')
                                ->content(fn ($record) => (string) self::stats($record)['total_activations']),

                            Placeholder::make('stats_new_first')
                                ->label('Новые: первый промокод')
                                ->content(fn ($record) => (string) self::stats($record)['new_first']),

                            Placeholder::make('stats_old_first')
                                ->label('Старые: первый промокод')
                                ->content(fn ($record) => (string) self::stats($record)['old_first']),

                            Placeholder::make('stats_old_repeat')
                                ->label('Старые: повторный промокод')
                                ->content(fn ($record) => (string) self::stats($record)['old_repeat']),
                        ]),
                    ]),

                Section::make('LosReferidos')
                    ->schema([
                        TextInput::make('partner_id')
                            ->label('ID партнёра')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('lr_offer_id')
                            ->label('ID оффера в LR')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->visible(fn ($record) => $record?->partner_id !== null),
            ]);
    }

    /**
     * Считает агрегированную статистику по активациям промокода.
     * Кэшируется на запрос, чтобы не дёргать базу для каждого Placeholder.
     */
    protected static function stats(?Promocode $promocode): array
    {
        if (! $promocode) {
            return self::emptyStats();
        }

        static $cache = [];
        if (isset($cache[$promocode->id])) {
            return $cache[$promocode->id];
        }

        $activations = BonusTransaction::query()
            ->with('payment:id,amount')
            ->where('promocode_id', $promocode->id)
            ->where('type', BonusTransaction::TYPE_CREDIT)
            ->get(['id', 'client_id', 'payment_id', 'amount', 'created_at']);

        $totalDeposits = 0.0;
        $totalBonuses = 0.0;
        $newFirst = 0;
        $oldFirst = 0;
        $oldRepeat = 0;

        $clientIds = $activations->pluck('client_id')->unique()->values()->all();

        $clientsRegistered = DB::table('clients')
            ->whereIn('id', $clientIds)
            ->pluck('created_at', 'id');

        // Для каждого клиента — самая ранняя активация любого промокода
        $firstAnyActivation = DB::table('bonus_transactions')
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('promocode_id')
            ->where('type', BonusTransaction::TYPE_CREDIT)
            ->select('client_id', DB::raw('MIN(created_at) as first_at'), DB::raw('MIN(promocode_id) as first_promo'))
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        // Точный promocode_id первой активации
        $firstPromoByClient = [];
        foreach ($clientIds as $cid) {
            $row = DB::table('bonus_transactions')
                ->where('client_id', $cid)
                ->whereNotNull('promocode_id')
                ->where('type', BonusTransaction::TYPE_CREDIT)
                ->orderBy('created_at')
                ->first(['promocode_id', 'created_at']);
            if ($row) {
                $firstPromoByClient[$cid] = $row;
            }
        }

        foreach ($activations as $tx) {
            $totalDeposits += (float) ($tx->payment?->amount ?? 0);
            $totalBonuses += (float) $tx->amount;

            $first = $firstPromoByClient[$tx->client_id] ?? null;
            $isFirstEver = $first && (int) $first->promocode_id === (int) $promocode->id;

            if ($isFirstEver) {
                $registeredAt = $clientsRegistered[$tx->client_id] ?? null;
                $isNew = $registeredAt
                    && abs(strtotime((string) $registeredAt) - strtotime((string) $tx->created_at)) <= 86400;

                if ($isNew) {
                    $newFirst++;
                } else {
                    $oldFirst++;
                }
            } else {
                $oldRepeat++;
            }
        }

        return $cache[$promocode->id] = [
            'total_deposits' => $totalDeposits,
            'total_bonuses' => $totalBonuses,
            'total_activations' => $activations->count(),
            'new_first' => $newFirst,
            'old_first' => $oldFirst,
            'old_repeat' => $oldRepeat,
        ];
    }

    protected static function emptyStats(): array
    {
        return [
            'total_deposits' => 0.0,
            'total_bonuses' => 0.0,
            'total_activations' => 0,
            'new_first' => 0,
            'old_first' => 0,
            'old_repeat' => 0,
        ];
    }
}
