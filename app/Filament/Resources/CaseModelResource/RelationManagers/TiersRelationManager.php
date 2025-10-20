<?php

namespace App\Filament\Resources\CaseModelResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CaseModelResource;
use App\Models\CaseItem;
use App\Models\ClientInventoryItem;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;

class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';
    
    protected static ?string $title = 'Уровни призов';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название уровня')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Цена уровня')
                    ->required()
                    ->numeric()
                    ->prefix('₽'),
                TextInput::make('probability')
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
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('price', 'asc'))
            ->poll('5s') // Обновляем таблицу каждые 5 секунд
            ->columns([
                TextColumn::make('name')
                    ->label('Название уровня'),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB'),
                TextColumn::make('probability')
                    ->label('Вероятность')
                    ->suffix('%'),
                TextColumn::make('items_count')
                    ->label('Предметов')
                    ->getStateUsing(function ($record) {
                        return $record->items()->count();
                    }),
            ])
            ->defaultSort('price', 'asc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить уровень')
                    ->after(function () {
                        // Обновляем таблицу после добавления нового уровня
                        $this->dispatch('refreshTable');
                    }),
            ])
            ->recordActions([
                Action::make('manage_items')
                    ->label('Управление предметами')
                    ->icon('heroicon-m-cube')
                    ->url(fn ($record) => '/admin/case-items?' . http_build_query([
                        'tableFilters' => [
                            'case_id' => ['value' => $record->case_id],
                            'tier_id' => ['value' => $record->id],
                        ]
                    ]))
                    ->openUrlInNewTab(true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
