<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class PremiumSettingsSeeder extends Seeder
{
    /**
     * Настройки премиум-подписки
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'premium_case_discount_low',
                'value' => '10',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Скидка на кейсы до порога (в %)',
            ],
            [
                'key' => 'premium_case_discount_high',
                'value' => '5',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Скидка на кейсы выше порога (в %)',
            ],
            [
                'key' => 'premium_case_discount_threshold',
                'value' => '500',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Порог цены кейса для разделения скидок (₽)',
            ],
            [
                'key' => 'premium_marketplace_fee',
                'value' => '6',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Комиссия маркетплейса для премиум-пользователей (в %)',
            ],
            [
                'key' => 'premium_withdraw_fee',
                'value' => '6',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Комиссия вывода для премиум-пользователей (в %)',
            ],
            [
                'key' => 'withdraw_fee_percent',
                'value' => '7',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Комиссия вывода стандартная (в %)',
            ],
            [
                'key' => 'anti_unluck_threshold',
                'value' => '10',
                'type' => SiteSetting::TYPE_NUMBER,
                'description' => 'Анти-анлак: количество неокупов подряд для бесплатного открытия',
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
