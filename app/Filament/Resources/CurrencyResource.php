<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Exception;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Artisan;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';
    
    protected static ?string $navigationLabel = 'Валюты';
    
    protected static ?string $modelLabel = 'валюта';
    
    protected static ?string $pluralModelLabel = 'валюты';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Российский рубль'),
                    
                TextInput::make('symbol')
                    ->label('Символ')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('₽'),
                    
                TextInput::make('code')
                    ->label('Код валюты')
                    ->required()
                    ->maxLength(3)
                    ->rule('regex:/^[A-Z]{3}$/')
                    ->placeholder('RUB')
                    ->helperText('Трёхбуквенный код валюты (ISO 4217)'),
                    
                TextInput::make('exchange_rate')
                    ->label('Курс к основной валюте')
                    ->required()
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0.0001)
                    ->default(1.0000)
                    ->helperText('Курс относительно основной валюты'),
                    
                Toggle::make('is_primary')
                    ->label('Основная валюта')
                    ->helperText('Может быть только одна основная валюта'),
                    
                Toggle::make('is_active')
                    ->label('Активная')
                    ->default(true)
                    ->helperText('Основная валюта всегда активна'),
                    
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0)
                    ->helperText('Меньшее значение = выше в списке'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('symbol')
                    ->label('Символ')
                    ->searchable(),
                    
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->badge()
                    ->color('primary'),
                    
                TextColumn::make('exchange_rate')
                    ->label('Курс')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                    
                IconColumn::make('is_primary')
                    ->label('Основная')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                    
                IconColumn::make('is_active')
                    ->label('Активная')
                    ->boolean()
                    ->sortable(),
                    
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активные')
                    ->placeholder('Все валюты')
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные'),
            ])
            ->headerActions([
                Action::make('update_rates')
                    ->label('Обновить курсы')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Обновить курсы валют')
                    ->modalDescription('Курсы будут получены из внешнего API. Это может занять несколько секунд.')
                    ->modalSubmitActionLabel('Обновить')
                    ->action(function () {
                        try {
                            $service = app(CurrencyService::class);
                            $results = $service->updateExchangeRates();
                            
                            $updated = count(array_filter($results, fn($r) => $r['status'] === 'updated'));
                            $notFound = count(array_filter($results, fn($r) => $r['status'] === 'not_found'));
                            
                            if ($updated > 0) {
                                Notification::make()
                                    ->title('Курсы обновлены')
                                    ->body("Успешно обновлено курсов: {$updated}" . ($notFound > 0 ? ", не найдено: {$notFound}" : ''))
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Нет обновлений')
                                    ->body('Не удалось обновить курсы валют')
                                    ->warning()
                                    ->send();
                            }
                            
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Ошибка обновления')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
