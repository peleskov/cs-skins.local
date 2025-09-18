<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatBanResource\Pages;
use App\Filament\Resources\ChatBanResource\RelationManagers;
use App\Models\ChatBan;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

class ChatBanResource extends Resource
{
    protected static ?string $model = ChatBan::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationLabel = 'Баны чата';

    protected static ?string $modelLabel = 'Бан чата';

    protected static ?string $pluralModelLabel = 'Баны чата';

    protected static ?string $navigationGroup = 'Чат';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')
                    ->label('Пользователь')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (Client $record): string => "{$record->name} (ID: {$record->id})"),

                Forms\Components\DateTimePicker::make('banned_until')
                    ->label('Забанен до')
                    ->helperText('Оставьте пустым для постоянного бана')
                    ->native(false),

                Forms\Components\Textarea::make('reason')
                    ->label('Причина бана')
                    ->maxLength(255)
                    ->rows(3),

                Forms\Components\Hidden::make('banned_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Пользователь')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('banned_until')
                    ->label('Забанен до')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Постоянный бан')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->getStateUsing(fn (ChatBan $record): bool => $record->isActive()),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Причина')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('bannedBy.name')
                    ->label('Забанил')
                    ->default('Система'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Статус')
                    ->options([
                        '1' => 'Активные',
                        '0' => 'Истекшие',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === '1') {
                            return $query->active();
                        } elseif ($data['value'] === '0') {
                            return $query->where(function ($q) {
                                $q->whereNotNull('banned_until')
                                  ->where('banned_until', '<=', now());
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Разбанить')
                    ->modalHeading('Разбанить пользователя')
                    ->modalDescription('Вы уверены, что хотите разбанить этого пользователя?')
                    ->modalSubmitActionLabel('Разбанить'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Разбанить выбранных')
                        ->modalHeading('Разбанить выбранных пользователей')
                        ->modalSubmitActionLabel('Разбанить'),
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
            'index' => Pages\ListChatBans::route('/'),
            'create' => Pages\CreateChatBan::route('/create'),
            'edit' => Pages\EditChatBan::route('/{record}/edit'),
        ];
    }
}
