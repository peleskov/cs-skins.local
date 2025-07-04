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
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

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
                    ->suffix('₽'),
                    
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
                    
                BooleanColumn::make('is_verified')
                    ->label('Верифицирован'),
                    
                BooleanColumn::make('is_bot')
                    ->label('Бот'),
                    
                TextColumn::make('locale')
                    ->label('Язык')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ru' => 'success',
                        'en' => 'primary',
                        default => 'secondary',
                    }),
                    
                TextColumn::make('created_at')
                    ->label('Дата регистрации')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
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
