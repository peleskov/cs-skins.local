<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannedWordResource\Pages;
use App\Filament\Resources\BannedWordResource\RelationManagers;
use App\Models\BannedWord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BannedWordResource extends Resource
{
    protected static ?string $model = BannedWord::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Запрещенные слова';

    protected static ?string $modelLabel = 'Запрещенное слово';

    protected static ?string $pluralModelLabel = 'Запрещенные слова';

    protected static ?string $navigationGroup = 'Чат';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('word')
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
                Tables\Columns\TextColumn::make('word')
                    ->label('Слово')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Добавлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListBannedWords::route('/'),
            'create' => Pages\CreateBannedWord::route('/create'),
            'edit' => Pages\EditBannedWord::route('/{record}/edit'),
        ];
    }
}
