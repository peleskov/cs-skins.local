<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

class CustomDashboard extends Dashboard
{
    /**
     * Виджет «Добро пожаловать» (AccountWidget) показываем всем.
     * Остальные виджеты — только super_admin.
     */
    public function getWidgets(): array
    {
        $user = auth()->user();
        if ($user && $user->hasRole('super_admin')) {
            return parent::getWidgets();
        }

        return [AccountWidget::class];
    }
}
