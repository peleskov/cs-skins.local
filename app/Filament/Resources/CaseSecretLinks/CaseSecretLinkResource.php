<?php

namespace App\Filament\Resources\CaseSecretLinks;

use App\Filament\Resources\CaseSecretLinks\Pages\ManageCaseSecretLinks;
use App\Models\CaseSecretLink;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CaseSecretLinkResource extends Resource
{
    protected static ?string $model = CaseSecretLink::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Скрытые ссылки';

    protected static ?string $modelLabel = 'Скрытая ссылка';

    protected static ?string $pluralModelLabel = 'Скрытые ссылки';

    protected static string|\UnitEnum|null $navigationGroup = 'Кейсы';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Метка')
                ->helperText('Опционально — для понимания где используется')
                ->maxLength(255),

            DateTimePicker::make('expires_at')
                ->label('Действует до')
                ->helperText('Пусто — бессрочно'),

            TextInput::make('max_visits')
                ->label('Лимит переходов')
                ->numeric()
                ->minValue(1)
                ->helperText('Пусто — без ограничения'),

            Toggle::make('is_active')
                ->label('Активна')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Метка')
                    ->placeholder('—'),

                TextColumn::make('token')
                    ->label('URL')
                    ->fontFamily('mono')
                    ->state(fn ($record) => url('/cases/secret/'.$record->token))
                    ->copyable()
                    ->copyableState(fn ($record) => url('/cases/secret/'.$record->token))
                    ->copyMessage('URL скопирован')
                    ->limit(50)
                    ->tooltip('Клик копирует URL'),

                TextColumn::make('visits')
                    ->label('Переходы')
                    ->state(fn ($record) => $record->visits_count.' / '.($record->max_visits ?? '∞')),

                TextColumn::make('expires_at')
                    ->label('Действует до')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('бессрочно'),

                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCaseSecretLinks::route('/'),
        ];
    }
}
