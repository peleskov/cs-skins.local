<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\BannedWordResource\Pages\ListBannedWords;
use App\Filament\Resources\BannedWordResource\Pages\CreateBannedWord;
use App\Filament\Resources\BannedWordResource\Pages\EditBannedWord;
use App\Filament\Resources\BannedWordResource\Pages;
use App\Filament\Resources\BannedWordResource\RelationManagers;
use App\Models\BannedWord;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BannedWordResource extends Resource
{
    protected static ?string $model = BannedWord::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Запрещенные слова';

    protected static ?string $modelLabel = 'Запрещенное слово';

    protected static ?string $pluralModelLabel = 'Запрещенные слова';

    protected static string | \UnitEnum | null $navigationGroup = 'Чат';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->label('Слово')
                    ->required()
                    ->maxLength(100)
                    ->unique(BannedWord::class, 'word', ignoreRecord: true)
                    ->helperText('Слово будет автоматически заблокировано в чате'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('word')
                    ->label('Слово')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Добавлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBannedWords::route('/'),
            'create' => CreateBannedWord::route('/create'),
            'edit' => EditBannedWord::route('/{record}/edit'),
        ];
    }
}
