<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    
    protected static ?string $navigationLabel = 'Валюты';
    
    protected static ?string $modelLabel = 'валюта';
    
    protected static ?string $pluralModelLabel = 'валюты';
    
    protected static ?string $navigationGroup = 'Настройки';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Российский рубль'),
                    
                Forms\Components\TextInput::make('symbol')
                    ->label('Символ')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('₽'),
                    
                Forms\Components\TextInput::make('code')
                    ->label('Код валюты')
                    ->required()
                    ->maxLength(3)
                    ->rule('regex:/^[A-Z]{3}$/')
                    ->placeholder('RUB')
                    ->helperText('Трёхбуквенный код валюты (ISO 4217)'),
                    
                Forms\Components\TextInput::make('exchange_rate')
                    ->label('Курс к основной валюте')
                    ->required()
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0.0001)
                    ->default(1.0000)
                    ->helperText('Курс относительно основной валюты'),
                    
                Forms\Components\Toggle::make('is_primary')
                    ->label('Основная валюта')
                    ->helperText('Может быть только одна основная валюта'),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('Активная')
                    ->default(true)
                    ->helperText('Основная валюта всегда активна'),
                    
                Forms\Components\TextInput::make('sort_order')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Символ')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Курс')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Основная')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активная')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активные')
                    ->placeholder('Все валюты')
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('update_rates')
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
                            
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка обновления')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
