<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Client;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\CaseItemResource\Pages\ListCaseItems;
use App\Filament\Resources\CaseItemResource\Pages;
use App\Models\CaseItem;
use App\Models\CaseModel;
use App\Models\CaseTier;
use App\Models\ClientInventoryItem;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CaseItemResource extends Resource
{
    protected static ?string $model = CaseItem::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-m-cube';
    
    protected static ?string $navigationLabel = 'Предметы кейсов';
    
    protected static ?string $modelLabel = 'Предмет кейса';
    
    protected static ?string $pluralModelLabel = 'Предметы кейсов';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Маркетплейс';
    
    protected static ?int $navigationSort = 4;
    
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        // Форма больше не нужна, так как мы используем чекбоксы в таблице
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $tableFilters = request()->get('tableFilters', []);
        $currentCaseId = $tableFilters['case_id']['value'] ?? null;
        $currentTierId = $tableFilters['tier_id']['value'] ?? null;
        
        return $table
            ->query(function () use ($currentCaseId, $currentTierId) {
                // Показываем предметы ботов, которые не используются в других кейсах
                return ClientInventoryItem::query()
                    ->whereHas('client', function ($query) {
                        $query->where('is_bot', true);
                    })
                    ->where(function ($query) use ($currentCaseId, $currentTierId) {
                        // Предметы, которые не используются ни в одном кейсе
                        $query->whereNotExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('case_items')
                                ->whereColumn('case_items.inventory_item_id', 'client_inventory_items.id');
                        });
                        
                        // ИЛИ предметы, которые используются только в текущем кейсе и уровне
                        if ($currentCaseId && $currentTierId) {
                            $query->orWhereExists(function ($subQuery) use ($currentCaseId, $currentTierId) {
                                $subQuery->select(DB::raw(1))
                                    ->from('case_items')
                                    ->whereColumn('case_items.inventory_item_id', 'client_inventory_items.id')
                                    ->where('case_items.case_id', $currentCaseId)
                                    ->where('case_items.tier_id', $currentTierId);
                            });
                        }
                    });
            })
            ->modifyQueryUsing(function (Builder $query) {
                // Показываем только предметы с ценой через market_hash_name
                return $query->whereIn('client_inventory_items.market_hash_name', function ($subQuery) {
                    $subQuery->select('market_hash_name')
                        ->from('steam_market_items')
                        ->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('steam_price_history')
                                ->whereColumn('steam_price_history.steam_market_item_id', 'steam_market_items.id');
                        });
                });
            })
            ->columns([
                CheckboxColumn::make('is_in_tier')
                    ->label('В уровне')
                    ->getStateUsing(function ($record, $livewire) {
                        return in_array($record->id, $livewire->selectedItems ?? []);
                    })
                    ->updateStateUsing(function ($record, $state, $livewire) {
                        $livewire->toggleItem($record->id);
                        return false;
                    }),
                ImageColumn::make('icon_url')
                    ->label('Изображение')
                    ->size(50)
                    ->square()
                    ->getStateUsing(function ($record) {
                        if (empty($record->icon_url)) {
                            return null;
                        }
                        return 'https://community.steamstatic.com/economy/image/' . $record->icon_url;
                    })
                    ->extraImgAttributes(['class' => 'object-contain']),
                TextColumn::make('item_name')
                    ->label('Предмет')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('client.name')
                    ->label('Владелец')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->client_id
                        ? route('filament.admin.resources.clients.edit', ['record' => $record->client_id])
                        : null),
                TextColumn::make('float_value')
                    ->label('Float')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 4))
                    ->sortable(),
                TextColumn::make('current_price')
                    ->label('Цена')
                    ->getStateUsing(function ($record) {
                        $price = $record->getCurrentPrice();
                        return $price ? number_format($price, 2) . ' ₽' : '-';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('steam_market_items', 'client_inventory_items.market_hash_name', '=', 'steam_market_items.market_hash_name')
                            ->leftJoin('steam_price_history', function ($join) {
                                $join->on('steam_market_items.id', '=', 'steam_price_history.steam_market_item_id')
                                    ->whereRaw('steam_price_history.id = (SELECT MAX(id) FROM steam_price_history sph WHERE sph.steam_market_item_id = steam_market_items.id)');
                            })
                            ->orderBy('steam_price_history.price', $direction)
                            ->select('client_inventory_items.*');
                    }),
            ])
            ->defaultSort('current_price', 'asc')
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Владелец')
                    ->options(function () {
                        return Client::where('is_bot', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable(),
                TernaryFilter::make('selected')
                    ->label('Выбранные')
                    ->placeholder('Все')
                    ->trueLabel('Только выбранные')
                    ->falseLabel('Только не выбранные')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('client_inventory_items.id', function ($subQuery) use ($currentCaseId, $currentTierId) {
                            $subQuery->select('inventory_item_id')
                                ->from('case_items')
                                ->where('case_id', $currentCaseId)
                                ->where('tier_id', $currentTierId);
                        }),
                        false: fn (Builder $query) => $query->whereNotIn('client_inventory_items.id', function ($subQuery) use ($currentCaseId, $currentTierId) {
                            $subQuery->select('inventory_item_id')
                                ->from('case_items')
                                ->where('case_id', $currentCaseId)
                                ->where('tier_id', $currentTierId);
                        }),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->selectCurrentPageOnly();
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
            'index' => ListCaseItems::route('/'),
        ];
    }
}
