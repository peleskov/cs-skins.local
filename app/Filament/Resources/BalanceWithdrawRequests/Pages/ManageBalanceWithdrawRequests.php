<?php

namespace App\Filament\Resources\BalanceWithdrawRequests\Pages;

use App\Filament\Resources\BalanceWithdrawRequests\BalanceWithdrawRequestResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageBalanceWithdrawRequests extends ManageRecords
{
    protected static string $resource = BalanceWithdrawRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limits')
                ->label(fn () => 'Лимиты вывода'.$this->limitsBadge())
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->modalHeading('Настройки лимитов на вывод')
                ->modalSubmitActionLabel('Сохранить')
                ->fillForm(fn () => [
                    'enabled' => (bool) SiteSetting::get('withdraw_enabled', true),
                    'min' => (float) SiteSetting::get('minimum_withdraw_amount', 100),
                    'daily_total' => (float) SiteSetting::get('withdraw_limit_daily_total', 0),
                    'daily_per_user' => (float) SiteSetting::get('withdraw_limit_daily_per_user', 0),
                    'hourly_total' => (float) SiteSetting::get('withdraw_limit_hourly_total', 0),
                ])
                ->schema([
                    Toggle::make('enabled')
                        ->label('Вывод средств включён')
                        ->helperText('Если выключен — пользователи видят сообщение о техработах')
                        ->inline(false),
                    TextInput::make('min')
                        ->label('Минимальная сумма вывода')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₽')
                        ->required(),
                    TextInput::make('daily_total')
                        ->label('Лимит на 24 часа (все пользователи)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₽')
                        ->helperText('0 = без ограничения'),
                    TextInput::make('daily_per_user')
                        ->label('Лимит на 24 часа (один пользователь)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₽')
                        ->helperText('0 = без ограничения'),
                    TextInput::make('hourly_total')
                        ->label('Лимит на 1 час (все пользователи)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₽')
                        ->helperText('0 = без ограничения'),
                ])
                ->action(function (array $data): void {
                    SiteSetting::set('withdraw_enabled', (bool) ($data['enabled'] ?? true), SiteSetting::TYPE_BOOLEAN, 'Глобальный тумблер вывода средств');
                    SiteSetting::set('minimum_withdraw_amount', $data['min'] ?? 100, SiteSetting::TYPE_NUMBER, 'Минимальная сумма вывода ₽');
                    SiteSetting::set('withdraw_limit_daily_total', $data['daily_total'] ?? 0, SiteSetting::TYPE_NUMBER, 'Лимит вывода за 24ч (все)');
                    SiteSetting::set('withdraw_limit_daily_per_user', $data['daily_per_user'] ?? 0, SiteSetting::TYPE_NUMBER, 'Лимит вывода за 24ч (один пользователь)');
                    SiteSetting::set('withdraw_limit_hourly_total', $data['hourly_total'] ?? 0, SiteSetting::TYPE_NUMBER, 'Лимит вывода за 1ч (все)');

                    Notification::make()->title('Настройки сохранены')->success()->send();
                }),
        ];
    }

    protected function limitsBadge(): string
    {
        $d = (float) SiteSetting::get('withdraw_limit_daily_total', 0);
        $p = (float) SiteSetting::get('withdraw_limit_daily_per_user', 0);
        $h = (float) SiteSetting::get('withdraw_limit_hourly_total', 0);

        $fmt = fn ($v) => $v > 0 ? number_format($v, 0, '.', ' ').'₽' : '∞';

        return ' (24ч/all: '.$fmt($d).', 24ч/user: '.$fmt($p).', 1ч/all: '.$fmt($h).')';
    }
}
