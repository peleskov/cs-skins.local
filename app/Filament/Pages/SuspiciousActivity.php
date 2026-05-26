<?php

namespace App\Filament\Pages;

use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuspiciousActivity extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Подозрительная активность';

    protected static ?string $title = 'Подозрительная активность';

    protected static string|\UnitEnum|null $navigationGroup = 'Отчёты';

    protected static ?int $navigationSort = 110;

    protected string $view = 'filament.pages.suspicious-activity';

    public ?array $filterData = [
        'period' => 'day',
        'date_from' => null,
        'date_to' => null,
        'threshold' => null,
        'limit' => 50,
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('super_admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->filterForm->fill();
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)->schema([
                    Select::make('period')
                        ->label('Период')
                        ->options([
                            'hour' => 'За час',
                            'day' => 'За 24 часа',
                            'week' => 'За неделю',
                            'custom' => 'Произвольный',
                        ])
                        ->default('day')
                        ->live()
                        ->native(false),

                    DatePicker::make('date_from')
                        ->label('С')
                        ->visible(fn ($get) => $get('period') === 'custom'),

                    DatePicker::make('date_to')
                        ->label('По')
                        ->visible(fn ($get) => $get('period') === 'custom'),

                    TextInput::make('threshold')
                        ->label('Порог подсветки, ₽')
                        ->numeric()
                        ->placeholder('Например 30000')
                        ->live(debounce: 500),

                    TextInput::make('limit')
                        ->label('Кол-во строк')
                        ->numeric()
                        ->default(50)
                        ->minValue(10)
                        ->maxValue(500)
                        ->live(debounce: 500),
                ]),
            ])
            ->statePath('filterData');
    }

    protected function getForms(): array
    {
        return ['filterForm'];
    }

    protected function getTimeRange(): array
    {
        $period = $this->filterData['period'] ?? 'day';

        return match ($period) {
            'hour' => [now()->subHour(), now()],
            'week' => [now()->subWeek(), now()],
            'custom' => [
                $this->filterData['date_from'] ? Carbon::parse($this->filterData['date_from'])->startOfDay() : now()->subDay(),
                $this->filterData['date_to'] ? Carbon::parse($this->filterData['date_to'])->endOfDay() : now(),
            ],
            default => [now()->subDay(), now()],
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function () {
                [$from, $to] = $this->getTimeRange();
                $limit = (int) ($this->filterData['limit'] ?? 50);

                // Кейсы: сумма брутто (price предмета) — без бесплатных, без подкрученных
                $cases = DB::table('case_opens')
                    ->join('case_inventory_items', 'case_opens.case_inventory_item_id', '=', 'case_inventory_items.id')
                    ->join('clients', 'clients.id', '=', 'case_opens.client_id')
                    ->where('case_opens.is_free', false)
                    ->where(function ($q) {
                        $q->where('clients.rigging_enabled', false)
                            ->orWhereNull('clients.rigging_until')
                            ->orWhere('clients.rigging_until', '<=', now());
                    })
                    ->whereBetween('case_opens.created_at', [$from, $to])
                    ->groupBy('case_opens.client_id')
                    ->select(
                        'case_opens.client_id',
                        DB::raw('SUM(case_inventory_items.price) as cases_sum'),
                        DB::raw('COUNT(*) as cases_count'),
                    )
                    ->get()
                    ->keyBy('client_id');

                // Апгрейды: сумма target_price для выигрышных
                $upgrades = DB::table('upgrades')
                    ->where('result', 'win')
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('client_id')
                    ->select(
                        'client_id',
                        DB::raw('SUM(target_price) as upgrades_sum'),
                        DB::raw('COUNT(*) as upgrades_count'),
                    )
                    ->get()
                    ->keyBy('client_id');

                $clientIds = $cases->keys()->merge($upgrades->keys())->unique()->values();
                if ($clientIds->isEmpty()) {
                    return new Collection;
                }

                $clients = Client::whereIn('id', $clientIds)
                    ->select('id', 'name', 'email', 'steam_avatar')
                    ->get()
                    ->keyBy('id');

                $rows = $clientIds->map(function ($cid) use ($cases, $upgrades, $clients) {
                    $client = $clients->get($cid);
                    if (! $client) {
                        return null;
                    }

                    $casesSum = (float) ($cases[$cid]->cases_sum ?? 0);
                    $upgradesSum = (float) ($upgrades[$cid]->upgrades_sum ?? 0);
                    $casesCount = (int) ($cases[$cid]->cases_count ?? 0);
                    $upgradesCount = (int) ($upgrades[$cid]->upgrades_count ?? 0);

                    return [
                        'id' => $cid,
                        'client_id' => $cid,
                        'name' => $client->name,
                        'email' => $client->email,
                        'cases_sum' => $casesSum,
                        'upgrades_sum' => $upgradesSum,
                        'total_sum' => $casesSum + $upgradesSum,
                        'cases_count' => $casesCount,
                        'upgrades_count' => $upgradesCount,
                        'ops_count' => $casesCount + $upgradesCount,
                    ];
                })
                    ->filter()
                    ->sortByDesc('total_sum')
                    ->take($limit)
                    ->values()
                    ->keyBy('id');

                return $rows;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Пользователь')
                    ->description(fn ($record) => $record['email'])
                    ->url(fn ($record) => route('filament.admin.resources.clients.edit', ['record' => $record['client_id']]))
                    ->openUrlInNewTab(),

                TextColumn::make('cases_sum')
                    ->label('Кейсы, ₽')
                    ->money('RUB')
                    ->alignEnd(),

                TextColumn::make('upgrades_sum')
                    ->label('Апгрейды, ₽')
                    ->money('RUB')
                    ->alignEnd(),

                TextColumn::make('total_sum')
                    ->label('Итого, ₽')
                    ->money('RUB')
                    ->weight('bold')
                    ->alignEnd()
                    ->color(function ($record) {
                        $threshold = (float) ($this->filterData['threshold'] ?? 0);
                        return $threshold > 0 && $record['total_sum'] >= $threshold ? 'danger' : null;
                    }),

                TextColumn::make('ops_count')
                    ->label('Операций')
                    ->state(fn ($record) => $record['cases_count'].' / '.$record['upgrades_count'])
                    ->tooltip('Кейсы / Апгрейды')
                    ->alignCenter(),
            ])
            ->paginated(false);
    }
}
