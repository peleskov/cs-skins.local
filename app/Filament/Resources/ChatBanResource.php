<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ChatBanResource\Pages\ListChatBans;
use App\Filament\Resources\ChatBanResource\Pages\CreateChatBan;
use App\Filament\Resources\ChatBanResource\Pages\EditChatBan;
use App\Filament\Resources\ChatBanResource\Pages;
use App\Filament\Resources\ChatBanResource\RelationManagers;
use App\Models\ChatBan;
use App\Models\Client;
use Filament\Forms;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationLabel = 'Баны чата';

    protected static ?string $modelLabel = 'Бан чата';

    protected static ?string $pluralModelLabel = 'Баны чата';

    protected static string | \UnitEnum | null $navigationGroup = 'Чат';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Пользователь')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (Client $record): string => "{$record->name} (ID: {$record->id})"),

                DateTimePicker::make('banned_until')
                    ->label('Забанен до')
                    ->helperText('Оставьте пустым для постоянного бана')
                    ->native(false),

                Textarea::make('reason')
                    ->label('Причина бана')
                    ->maxLength(255)
                    ->rows(3),

                Hidden::make('banned_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Пользователь')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => $record->client_id
                        ? route('filament.admin.resources.clients.edit', ['record' => $record->client_id])
                        : null),

                TextColumn::make('banned_until')
                    ->label('Забанен до')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Постоянный бан')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->getStateUsing(fn (ChatBan $record): bool => $record->isActive()),

                TextColumn::make('reason')
                    ->label('Причина')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('bannedBy.name')
                    ->label('Забанил')
                    ->default('Система'),

                TextColumn::make('created_at')
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Разбанить')
                    ->modalHeading('Разбанить пользователя')
                    ->modalDescription('Вы уверены, что хотите разбанить этого пользователя?')
                    ->modalSubmitActionLabel('Разбанить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
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
            'index' => ListChatBans::route('/'),
            'create' => CreateChatBan::route('/create'),
            'edit' => EditChatBan::route('/{record}/edit'),
        ];
    }
}
