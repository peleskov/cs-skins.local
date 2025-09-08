<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TierItemsManagementResource\Pages;
use App\Models\ClientInventoryItem;
use App\Models\CaseTier;
use App\Models\CaseItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TierItemsManagementResource extends Resource
{
    protected static ?string $model = ClientInventoryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Предметы уровня';
    
    protected static ?string $modelLabel = 'Предмет';
    
    protected static ?string $pluralModelLabel = 'Предметы';
    
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $tierId = request()->get('tier');
                
                return ClientInventoryItem::query()
                    ->whereHas('client', function ($query) {
                        $query->where('is_bot', true);
                    })
                    ->withExists([
                        'caseItems as is_selected' => function ($query) use ($tierId) {
                            $query->where('tier_id', $tierId);
                        }
                    ]);
            })
            ->columns([
                Tables\Columns\IconColumn::make('is_selected')
                    ->label('Выбран')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Название предмета')
                    ->searchable(),
                Tables\Columns\TextColumn::make('float_value')
                    ->label('Float')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 4))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_selected')
                    ->label('Статус')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_selected', true),
                        false: fn (Builder $query) => $query->where('is_selected', false),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn ($record) => $record->is_selected ? 'Удалить' : 'Добавить')
                    ->action(function ($record) {
                        $tierId = request()->get('tier');
                        $tier = CaseTier::findOrFail($tierId);
                        
                        if ($record->is_selected) {
                            CaseItem::where('tier_id', $tierId)
                                ->where('inventory_item_id', $record->id)
                                ->delete();
                        } else {
                            CaseItem::create([
                                'case_id' => $tier->case_id,
                                'tier_id' => $tierId,
                                'inventory_item_id' => $record->id,
                            ]);
                        }
                    })
                    ->color(fn ($record) => $record->is_selected ? 'danger' : 'success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('add_selected')
                    ->label('Добавить выбранные')
                    ->action(function ($records) {
                        $tierId = request()->route('tier');
                        $tier = CaseTier::findOrFail($tierId);
                        
                        foreach ($records as $record) {
                            CaseItem::firstOrCreate([
                                'case_id' => $tier->case_id,
                                'tier_id' => $tierId,
                                'inventory_item_id' => $record->id,
                            ]);
                        }
                    })
                    ->color('success'),
                Tables\Actions\BulkAction::make('remove_selected')
                    ->label('Удалить выбранные')
                    ->action(function ($records) {
                        $tierId = request()->route('tier');
                        
                        foreach ($records as $record) {
                            CaseItem::where('tier_id', $tierId)
                                ->where('inventory_item_id', $record->id)
                                ->delete();
                        }
                    })
                    ->color('danger')
                    ->requiresConfirmation(),
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
            'index' => Pages\ListTierItemsManagement::route('/tier-items'),
        ];
    }
}
