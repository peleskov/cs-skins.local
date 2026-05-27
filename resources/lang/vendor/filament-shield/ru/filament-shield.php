<?php

declare(strict_types=1);

return [
    /*
    |------------------------------------------------- -------------------------
    | Table Columns
    |------------------------------------------------- -------------------------
    */

    'column.name' => 'Имя',
    'column.guard_name' => 'Имя гварда',
    'column.roles' => 'Роли',
    'column.permissions' => 'Разрешения',
    'column.updated_at' => 'Обновлено',

    /*
    |------------------------------------------------- -------------------------
    | Form Fields
    |------------------------------------------------- -------------------------
    */

    'field.name' => 'Имя',
    'field.guard_name' => 'Имя гварда',
    'field.permissions' => 'Разрешения',
    'field.select_all.name' => 'Выбрать все',
    'field.select_all.message' => 'Включить все разрешения, которые <span class="text-primary font-medium">Доступны</span> для этой роли',

    /*
    |------------------------------------------------- -------------------------
    | Navigation & Resource
    |------------------------------------------------- -------------------------
    */

    'nav.group' => 'Filament Shield',
    'nav.role.label' => 'Роли',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Роль',
    'resource.label.roles' => 'Роли',

    /*
    |------------------------------------------------- -------------------------
    | Section & Tabs
    |------------------------------------------------- -------------------------
    */

    'section' => 'Сути',
    'resources' => 'Ресурсы',
    'widgets' => 'Виджеты',
    'pages' => 'Страницы',
    'custom' => 'Пользовательские разрешения',

    /*
    |------------------------------------------------- -------------------------
    | Messages
    |------------------------------------------------- -------------------------
    */

    'forbidden' => 'У вас нет доступа',

    /*
    |------------------------------------------------- -------------------------
    | Resource Permissions' Labels
    |------------------------------------------------- -------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Просмотр',
        'view_any' => 'Может смотреть любое',
        'create' => 'Создание',
        'update' => 'Обновление',
        'delete' => 'Удаление',
        'delete_any' => 'Может удалить любой',
        'force_delete' => 'Принудительно удалить',
        'force_delete_any' => 'Может принудительно удалить любой',
        'restore' => 'Восстановление',
        'reorder' => 'Изменение порядка',
        'restore_any' => 'Может восстановить любой',
        'replicate' => 'Копировать',

        // Кастомные affix'ы для ClientResource
        'topup_balance' => 'Кнопка: Пополнить баланс',
        'withdraw_balance' => 'Кнопка: Снять с баланса',
        'topup_bonus' => 'Кнопка: Пополнить бонусный баланс',
        'withdraw_bonus' => 'Кнопка: Снять с бонусного баланса',
        'block_withdraw' => 'Кнопка блокировки: Вывод',
        'block_items' => 'Кнопка блокировки: Предметы',
        'block_balance' => 'Кнопка блокировки: Баланс',
        'section_rigging' => 'Секция: Подкрутка',
        'section_partner' => 'Секция: Партнёр (LosReferidos)',

        // Поля формы клиента — View
        'view_field_name' => 'Поле "Имя": просмотр',
        'view_field_email' => 'Поле "Email": просмотр',
        'view_field_steam_id' => 'Поле "Steam ID": просмотр',
        'view_field_steam_avatar' => 'Поле "Аватар Steam": просмотр',
        'view_field_steam_trade_url' => 'Поле "Trade URL": просмотр',
        'view_field_balance' => 'Поле "Баланс": просмотр',
        'view_field_bonus_balance' => 'Поле "Бонусный баланс": просмотр',
        'view_field_telegram_id' => 'Поле "Telegram ID": просмотр',
        'view_field_is_verified' => 'Поле "Верифицирован": просмотр',
        'view_field_is_bot' => 'Поле "Бот": просмотр',
        'view_field_locale' => 'Поле "Язык": просмотр',
        'view_field_admin_comment' => 'Поле "Комментарий админа": просмотр',

        // Поля формы клиента — Edit (включает View)
        'update_field_name' => 'Поле "Имя": редактирование',
        'update_field_email' => 'Поле "Email": редактирование',
        'update_field_steam_id' => 'Поле "Steam ID": редактирование',
        'update_field_steam_avatar' => 'Поле "Аватар Steam": редактирование',
        'update_field_steam_trade_url' => 'Поле "Trade URL": редактирование',
        'update_field_balance' => 'Поле "Баланс": редактирование (не применимо — readonly)',
        'update_field_bonus_balance' => 'Поле "Бонусный баланс": редактирование (не применимо — readonly)',
        'update_field_telegram_id' => 'Поле "Telegram ID": редактирование',
        'update_field_is_verified' => 'Поле "Верифицирован": редактирование',
        'update_field_is_bot' => 'Поле "Бот": редактирование',
        'update_field_locale' => 'Поле "Язык": редактирование',
        'update_field_admin_comment' => 'Поле "Комментарий админа": редактирование',
    ],
];
