<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManagerRolesSeeder extends Seeder
{
    public function run(): void
    {
        // 6.3 Рекламный менеджер
        $adManager = Role::firstOrCreate(['name' => 'ad_manager', 'guard_name' => 'web']);
        $adManager->syncPermissions($this->existing([
            // Баннеры — полный CRUD
            'ViewAny:AdBanner', 'View:AdBanner', 'Create:AdBanner',
            'Update:AdBanner', 'Delete:AdBanner', 'DeleteAny:AdBanner',
            // Аналитика — только просмотр
            'View:Analytics',
            'View:AnalyticsStatsWidget',
            // Промокоды — просмотр и создание (без удаления и тонких финансовых правок)
            'ViewAny:Promocode', 'View:Promocode', 'Create:Promocode', 'Update:Promocode',
            'View:PromocodesReportWidget',
            // Клиенты — только просмотр (action «остановить вывод» — отдельным правом ниже)
            'ViewAny:Client', 'View:Client',
        ]));

        // 6.4 Партнёр-менеджер
        $partnerManager = Role::firstOrCreate(['name' => 'partner_manager', 'guard_name' => 'web']);
        $partnerManager->syncPermissions($this->existing([
            // Промокоды — только просмотр (row-scoping по assigned partners — в getEloquentQuery)
            'ViewAny:Promocode', 'View:Promocode',
            'View:PromocodesReportWidget',
            // Страница «Мои партнёры»
            'View:MyPartners',
        ]));
    }

    /**
     * Возвращает только реально существующие permissions из переданного списка.
     * Защита от рассинхрона со shield:generate.
     */
    protected function existing(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('name')->all();
    }
}
