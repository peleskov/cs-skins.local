<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Все настройки сайта.
     *
     * Идемпотентный сидер: использует firstOrCreate, существующие записи не трогает.
     * Подходит для прода — добавит только отсутствующие ключи.
     */
    public function run(): void
    {
        $settings = [
            // Комиссии
            ['key' => 'marketplace_fee_percent', 'value' => '5', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия маркетплейса P2P (%)'],
            ['key' => 'auction_fee_percent', 'value' => '5', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия аукционов (% с продавца)'],
            ['key' => 'bot_purchase_fee_percent', 'value' => '0', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия при быстрой продаже боту (%)'],
            ['key' => 'withdraw_fee_percent', 'value' => '7', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия вывода стандартная (в %)'],
            ['key' => 'upgrade_commission', 'value' => '15', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия апгрейда (%)'],

            // Транзакции и баланс
            ['key' => 'transaction_hold_days', 'value' => '7', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Количество дней холда для транзакций продавца'],
            ['key' => 'minimum_deposit_amount', 'value' => '1', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Минимальная сумма пополнения баланса в рублях'],
            ['key' => 'maximum_deposit_amount', 'value' => '50000', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Максимальная сумма пополнения баланса в рублях'],
            ['key' => 'card_payment_enabled', 'value' => '1', 'type' => SiteSetting::TYPE_BOOLEAN, 'description' => 'Включить/выключить возможность пополнения баланса с помощью карты'],
            ['key' => 'test_payment_enabled', 'value' => '1', 'type' => SiteSetting::TYPE_BOOLEAN, 'description' => 'Включить/выключить возможность пополнения баланса без оплаты'],
            ['key' => 'usd_course', 'value' => '100', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Курс 1 рубль = XXX USD'],

            // Инвентарь
            ['key' => 'inventory_sync_cooldown_minutes', 'value' => '1', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Время ожидания между синхронизациями инвентаря (в минутах)'],

            // Режимы
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => SiteSetting::TYPE_BOOLEAN, 'description' => 'Режим технических работ'],

            // Апгрейд
            ['key' => 'upgrade_min_chance', 'value' => '1', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Минимальный шанс апгрейда (%)'],
            ['key' => 'upgrade_max_chance', 'value' => '70', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Максимальный шанс апгрейда (%)'],
            ['key' => 'upgrade_target_mode', 'value' => 'virtual', 'type' => SiteSetting::TYPE_STRING, 'description' => 'Источник пула целей для апгрейда (virtual|market)'],

            // Кейсы
            ['key' => 'anti_unluck_threshold', 'value' => '10', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Анти-анлак: количество неокупов подряд для бесплатного открытия'],
            ['key' => 'case_feed_broadcast_delay', 'value' => '7', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Задержка публикации события открытия кейса в ленту (сек), чтобы не спойлерить выпадение до окончания анимации рулетки'],

            // Премиум
            ['key' => 'premium_case_discount_low', 'value' => '10', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Скидка на кейсы до порога (в %)'],
            ['key' => 'premium_case_discount_high', 'value' => '5', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Скидка на кейсы выше порога (в %)'],
            ['key' => 'premium_case_discount_threshold', 'value' => '500', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Порог цены кейса для разделения скидок (₽)'],
            ['key' => 'premium_marketplace_fee', 'value' => '6', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия маркетплейса для премиум-пользователей (в %)'],
            ['key' => 'premium_withdraw_fee', 'value' => '6', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Комиссия вывода для премиум-пользователей (в %)'],

            // Онлайн-счётчик
            ['key' => 'online_mode', 'value' => 'real_with_fake', 'type' => SiteSetting::TYPE_STRING, 'description' => 'Режим онлайн-счётчика'],
            ['key' => 'online_fake_base', 'value' => '100', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'База фейкового онлайна'],
            ['key' => 'online_fluctuation', 'value' => '80', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Амплитуда колебаний онлайна'],
            ['key' => 'online_window_seconds', 'value' => '300', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Окно активности онлайна (сек)'],
            ['key' => 'online_daily_profile', 'value' => '1', 'type' => SiteSetting::TYPE_BOOLEAN, 'description' => 'Суточный профиль онлайна'],
            ['key' => 'online_daily_amplitude', 'value' => '40', 'type' => SiteSetting::TYPE_NUMBER, 'description' => 'Амплитуда суточного профиля (%)'],

            // Контакты / интеграции
            ['key' => 'contact_emails', 'value' => 'info@s1temaker.ru', 'type' => SiteSetting::TYPE_STRING, 'description' => 'Email адреса для формы обратной связи (через запятую)'],
            ['key' => 'yandex_metrika', 'value' => '1', 'type' => SiteSetting::TYPE_STRING, 'description' => 'ID Яндекс Метрики'],
            ['key' => 'iframe_map', 'value' => 'https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d8991.198734822688!2d74.53682305036459!3d42.81922997732681!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2s!5e0!3m2!1sru!2ses!4v1758872732501!5m2!1sru!2ses', 'type' => SiteSetting::TYPE_STRING, 'description' => 'Код карты на странице контакты'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
