<?php

namespace App\Filament\Pages;

use App\Models\BonusTransaction;
use App\Models\Partner;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class MyPartners extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Мои партнёры';

    protected static ?string $title = 'Мои партнёры';

    protected static string|\UnitEnum|null $navigationGroup = 'Партнёры';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.my-partners';

    public ?array $filterData = ['promocode_id' => null];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('partner_manager') || $user->hasRole('super_admin'));
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
        $partnerIds = auth()->user()->partners()->pluck('partners.id');
        $promocodes = \App\Models\Promocode::whereIn('partner_id', $partnerIds)->pluck('code', 'id');

        return $schema
            ->components([
                Select::make('promocode_id')
                    ->label('Промокод')
                    ->options($promocodes)
                    ->placeholder('Все промокоды')
                    ->searchable()
                    ->live()
                    ->native(false),
            ])
            ->statePath('filterData');
    }

    protected function getForms(): array
    {
        return ['filterForm'];
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $partnerIds = $user->partners()->pluck('partners.id');
        $promocodeFilter = $this->filterData['promocode_id'] ?? null;

        $partners = Partner::whereIn('id', $partnerIds)
            ->get()
            ->map(function (Partner $partner) use ($promocodeFilter) {
                $promocodes = \App\Models\Promocode::where('partner_id', $partner->id)
                    ->select('id', 'code', 'is_active', 'used_count')
                    ->get();

                $promocodeIds = $promocodes->pluck('id');

                $activations = BonusTransaction::where('type', BonusTransaction::TYPE_CREDIT)
                    ->whereIn('promocode_id', $promocodeIds)
                    ->when($promocodeFilter, fn ($q) => $q->where('promocode_id', $promocodeFilter))
                    ->count();

                $totalBonus = (float) BonusTransaction::where('type', BonusTransaction::TYPE_CREDIT)
                    ->whereIn('promocode_id', $promocodeIds)
                    ->when($promocodeFilter, fn ($q) => $q->where('promocode_id', $promocodeFilter))
                    ->sum('amount');

                $totalDeposits = (float) DB::table('payments')
                    ->whereIn('promocode_id', $promocodeIds)
                    ->when($promocodeFilter, fn ($q) => $q->where('promocode_id', $promocodeFilter))
                    ->where('status', 'paid')
                    ->sum('amount');

                $referrals = $partner->referrals()->count();

                return [
                    'partner' => $partner,
                    'promocodes' => $promocodes,
                    'referrals' => $referrals,
                    'activations' => $activations,
                    'total_bonus' => $totalBonus,
                    'total_deposits' => $totalDeposits,
                ];
            });

        return [
            'partners' => $partners,
        ];
    }
}
