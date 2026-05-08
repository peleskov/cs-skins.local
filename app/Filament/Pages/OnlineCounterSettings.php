<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\OnlineCounterService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class OnlineCounterSettings extends Page implements HasForms
{
    use \BezhanSalleh\FilamentShield\Traits\HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Онлайн-счётчик';

    protected static ?string $title = 'Онлайн-счётчик';

    protected static string|\UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.online-counter-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'online_mode' => SiteSetting::get('online_mode', OnlineCounterService::MODE_REAL),
            'online_fake_base' => (int) SiteSetting::get('online_fake_base', 0),
            'online_fluctuation' => (int) SiteSetting::get('online_fluctuation', 0),
            'online_window_seconds' => (int) SiteSetting::get('online_window_seconds', 300),
            'online_daily_profile' => (bool) SiteSetting::get('online_daily_profile', false),
            'online_daily_amplitude' => (int) SiteSetting::get('online_daily_amplitude', 40),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('online_mode')
                    ->label('Режим онлайна')
                    ->options([
                        OnlineCounterService::MODE_REAL => 'Только реальный онлайн',
                        OnlineCounterService::MODE_REAL_WITH_FAKE => 'Реальный + накрутка',
                        OnlineCounterService::MODE_FAKE => 'Полностью фейковый',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('online_fake_base')
                    ->label('Базовое фейковое значение')
                    ->helperText('Прибавляется к реальному (или используется как базовое в режиме «Полностью фейковый»)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                TextInput::make('online_fluctuation')
                    ->label('Амплитуда колебаний (±)')
                    ->helperText('Случайное значение в диапазоне ±N добавляется при каждом обновлении (раз в 10 сек)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                TextInput::make('online_window_seconds')
                    ->label('Окно активности (сек)')
                    ->helperText('Пользователь считается онлайн, если был на сайте в последние N секунд')
                    ->numeric()
                    ->minValue(60)
                    ->default(300),

                Toggle::make('online_daily_profile')
                    ->label('Суточный профиль')
                    ->helperText('Пик онлайна около 20:00, минимум — около 08:00 (Europe/Moscow). Применяется только к фейковой части.'),

                TextInput::make('online_daily_amplitude')
                    ->label('Амплитуда профиля (%)')
                    ->helperText('Насколько фейковая часть растёт в пике и падает ночью. 40% → ночью 60%, в пике 140%.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(90)
                    ->default(40)
                    ->visible(fn ($get) => $get('online_daily_profile')),
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('online_mode', $data['online_mode'], SiteSetting::TYPE_STRING, 'Режим онлайн-счётчика');
        SiteSetting::set('online_fake_base', $data['online_fake_base'], SiteSetting::TYPE_NUMBER, 'База фейкового онлайна');
        SiteSetting::set('online_fluctuation', $data['online_fluctuation'], SiteSetting::TYPE_NUMBER, 'Амплитуда колебаний онлайна');
        SiteSetting::set('online_window_seconds', $data['online_window_seconds'], SiteSetting::TYPE_NUMBER, 'Окно активности онлайна (сек)');
        SiteSetting::set('online_daily_profile', $data['online_daily_profile'], SiteSetting::TYPE_BOOLEAN, 'Суточный профиль онлайна');
        SiteSetting::set('online_daily_amplitude', $data['online_daily_amplitude'] ?? 40, SiteSetting::TYPE_NUMBER, 'Амплитуда суточного профиля (%)');

        Cache::forget('online:current');

        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Сохранить')->submit('save'),
        ];
    }

    public function getCurrentOnline(): int
    {
        Cache::forget('online:current');

        return app(OnlineCounterService::class)->currentCount();
    }

    public function getRealOnline(): int
    {
        return app(OnlineCounterService::class)->realCount();
    }
}
