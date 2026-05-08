<?php

namespace App\Filament\Resources\Upgrades\Pages;

use App\Filament\Resources\Upgrades\UpgradeResource;
use App\Filament\Resources\Upgrades\Widgets\UpgradeStatsWidget;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageUpgrades extends ManageRecords
{
    protected static string $resource = UpgradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label(fn () => 'Настройки апгрейда — текущий источник: '.(SiteSetting::get('upgrade_target_mode', 'virtual') === 'market' ? 'маркетплейс' : 'виртуальные'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color(fn () => SiteSetting::get('upgrade_target_mode', 'virtual') === 'market' ? 'warning' : 'gray')
                ->modalHeading('Настройки апгрейда')
                ->modalSubmitActionLabel('Сохранить')
                ->fillForm(fn () => [
                    'upgrade_target_mode' => SiteSetting::get('upgrade_target_mode', 'virtual'),
                ])
                ->schema([
                    Select::make('upgrade_target_mode')
                        ->label('Источник целевых предметов')
                        ->options([
                            'virtual' => 'Виртуальные предметы (как сейчас)',
                            'market' => 'Активные предложения маркетплейса',
                        ])
                        ->helperText('В режиме «маркетплейс» пул целей формируется из активных листингов.')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    SiteSetting::set(
                        'upgrade_target_mode',
                        $data['upgrade_target_mode'],
                        SiteSetting::TYPE_STRING,
                        'Источник пула целей для апгрейда (virtual|market)'
                    );

                    Notification::make()->title('Настройки сохранены')->success()->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpgradeStatsWidget::class,
        ];
    }
}
