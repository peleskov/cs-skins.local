<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdBannerResource\Pages;
use App\Filament\Resources\AdBannerResource\RelationManagers;
use App\Models\AdBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;

class AdBannerResource extends Resource
{
    protected static ?string $model = AdBanner::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Рекламный баннер';

    protected static ?string $modelLabel = 'Рекламный баннер';

    protected static ?string $pluralModelLabel = 'Рекламные баннеры';

    protected static ?string $navigationGroup = 'Контент';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('image')
                    ->label('Картинка')
                    ->image()
                    ->directory('ad-banners')
                    ->visibility('public')
                    ->columnSpanFull(),
                Repeater::make('content')
                    ->label('Содержимое')
                    ->schema([
                        Select::make('lang')
                            ->label('Язык')
                            ->options([
                                'ru' => 'Русский',
                                'en' => 'English'
                            ])
                            ->required()
                            ->distinct(),
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->maxLength(255)
                            ->required(),
                        Textarea::make('text')
                            ->label('Текст')
                            ->rows(4)
                            ->required()
                    ])
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->label('Активный')
                    ->helperText('Только один баннер может быть активным')
                    ->default(false)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Картинка')
                    ->square()
                    ->size(60),
                ToggleColumn::make('active')
                    ->label('Активный')
                    ->onColor('success')
                    ->offColor('gray'),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListAdBanners::route('/'),
            'create' => Pages\CreateAdBanner::route('/create'),
            'edit' => Pages\EditAdBanner::route('/{record}/edit'),
        ];
    }
}
