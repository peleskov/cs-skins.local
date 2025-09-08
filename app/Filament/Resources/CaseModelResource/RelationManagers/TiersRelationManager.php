<?php

namespace App\Filament\Resources\CaseModelResource\RelationManagers;

use App\Filament\Resources\CaseModelResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';
    
    protected static ?string $title = 'Уровни призов';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название уровня')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->label('Цена уровня')
                    ->required()
                    ->numeric()
                    ->prefix('₽'),
                Forms\Components\TextInput::make('probability')
                    ->label('Вероятность выпадения')
                    ->required()
                    ->numeric()
                    ->suffix('%')
                    ->helperText('Вероятность выпадения этого уровня'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название уровня'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('probability')
                    ->label('Вероятность')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Предметов')
                    ->counts('items'),
            ])
            ->defaultSort('price', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить уровень'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_items')
                    ->label('Управление предметами')
                    ->icon('heroicon-m-cube')
                    ->url(fn ($record) => '/admin/case-items?' . http_build_query([
                        'tableFilters' => [
                            'case_id' => ['value' => $record->case_id],
                            'tier_id' => ['value' => $record->id],
                        ]
                    ]))
                    ->openUrlInNewTab(true),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
