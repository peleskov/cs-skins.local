# TODO: Steam API интеграция

## Цель
Реализовать возможность для пользователей выставлять на продажу скины из своего Steam инвентаря.

## Основные этапы

### 1. Настройка Steam API (1-2 дня)

#### 1.1 Получение доступов
- [ ] Получить Steam API ключ на https://steamcommunity.com/dev/apikey
- [ ] Зарегистрировать приложение для Steam OAuth
- [ ] Настроить redirect URI для авторизации

#### 1.2 Конфигурация Laravel
- [ ] Добавить в .env:
  ```
  STEAM_API_KEY=ваш_ключ
  STEAM_APP_ID=ваш_app_id
  STEAM_REDIRECT_URI=https://yourdomain.com/auth/steam/callback
  ```
- [ ] Установить пакет для Steam OAuth (например, invisnik/laravel-steam-auth)
- [ ] Настроить конфиг auth.php для Steam провайдера

### 2. Миграции базы данных (30 мин)

#### 2.1 Обновление таблицы users
- [ ] Создать миграцию для добавления Steam полей:
  ```php
  $table->string('steam_id')->nullable()->unique();
  $table->string('steam_username')->nullable();
  $table->string('steam_avatar_url')->nullable();
  $table->string('steam_profile_url')->nullable();
  $table->string('steam_trade_url')->nullable();
  $table->timestamp('steam_linked_at')->nullable();
  ```

#### 2.2 Обновление таблицы listings
- [ ] Добавить поля для Steam интеграции:
  ```php
  $table->string('steam_asset_id')->nullable();
  $table->string('steam_owner_id')->nullable();
  $table->text('inspect_url')->nullable();
  $table->json('steam_item_data')->nullable(); // дополнительные данные из Steam
  ```

### 3. Авторизация через Steam (1 день)

#### 3.1 Контроллер авторизации
- [ ] Создать SteamAuthController:
  - `redirectToSteam()` - перенаправление на Steam
  - `handleSteamCallback()` - обработка возврата от Steam
  - `linkSteamAccount()` - привязка Steam к существующему аккаунту
  - `unlinkSteamAccount()` - отвязка Steam аккаунта

#### 3.2 Роуты
- [ ] Добавить маршруты:
  ```php
  Route::get('/auth/steam', [SteamAuthController::class, 'redirectToSteam']);
  Route::get('/auth/steam/callback', [SteamAuthController::class, 'handleSteamCallback']);
  Route::post('/profile/steam/link', [SteamAuthController::class, 'linkSteamAccount']);
  Route::delete('/profile/steam/unlink', [SteamAuthController::class, 'unlinkSteamAccount']);
  ```

#### 3.3 Middleware
- [ ] Создать middleware для проверки привязки Steam аккаунта
- [ ] Применить к маршрутам создания лотов

### 4. Steam Inventory Service (1-2 дня)

#### 4.1 Сервис для работы с Steam API
- [ ] Создать `app/Services/SteamInventoryService.php`:
  - `getInventory($steamId)` - получение инвентаря CS2
  - `getItemDetails($appId, $assetId)` - детали конкретного предмета
  - `parseItemData($item)` - парсинг данных предмета
  - `validateOwnership($steamId, $assetId)` - проверка владения

#### 4.2 Методы сервиса
- [ ] Реализовать получение инвентаря:
  ```php
  // GET https://steamcommunity.com/inventory/{steamid}/730/2
  ```
- [ ] Фильтрация только CS2 предметов (appid = 730)
- [ ] Извлечение данных:
  - asset_id
  - classid, instanceid
  - market_hash_name
  - float_value (из inspect ссылки)
  - pattern_index
  - stickers (если есть)

#### 4.3 Кеширование
- [ ] Настроить кеш инвентаря на 10-15 минут
- [ ] Реализовать принудительное обновление кеша

### 5. Страница "Мой инвентарь" (1-2 дня)

#### 5.1 Контроллер инвентаря
- [ ] Создать `InventoryController`:
  - `index()` - отображение инвентаря
  - `refresh()` - обновление кеша инвентаря
  - `sellItem()` - форма создания лота

#### 5.2 Blade шаблон
- [ ] Создать `resources/views/inventory/index.blade.php`:
  - Проверка привязки Steam аккаунта
  - Отображение предметов в виде карточек
  - Фильтры по типу/редкости
  - Кнопки "Продать" для каждого предмета

#### 5.3 Vue компонент (опционально)
- [ ] Создать `InventoryGrid.vue` для динамической работы:
  - Загрузка инвентаря через API
  - Фильтрация и поиск
  - Модальное окно создания лота

### 6. Создание лотов из инвентаря (1 день)

#### 6.1 Контроллер лотов
- [ ] Обновить `ListingController`:
  - `createFromInventory($assetId)` - создание лота из предмета Steam
  - `validateSteamItem($steamId, $assetId)` - проверка владения
  - `calculateRecommendedPrice($item)` - рекомендуемая цена

#### 6.2 Форма создания лота
- [ ] Создать форму с полями:
  - Тип продажи (P2P / боту)
  - Цена (с рекомендацией)
  - Trade URL (если не указан)
  - Подтверждение владения

#### 6.3 Валидация
- [ ] Проверка что предмет есть в инвентаре
- [ ] Проверка что предмет не продается уже
- [ ] Проверка корректности Steam Trade URL

### 7. Генерация Inspect URL (30 мин)

#### 7.1 Сервис для Inspect URL
- [ ] Создать `InspectUrlService`:
  - `generate($steamId, $assetId, $d = null)` - генерация inspect ссылки
  - Формат: `steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S{steamId}A{assetId}D{d}`

#### 7.2 Интеграция
- [ ] Автоматическая генерация при создании лота
- [ ] Обновление SkinDetails.vue для использования реального inspect_url

### 8. Настройка Trade URL (30 мин)

#### 8.1 Страница настроек
- [ ] Добавить в профиль поле для Steam Trade URL
- [ ] Валидация формата Trade URL
- [ ] Инструкции как получить Trade URL

#### 8.2 Автоматическое определение
- [ ] Попытка получить Trade URL через Steam API (если доступно)
- [ ] Уведомление пользователя о необходимости настройки

### 9. Безопасность и валидация (1 день)

#### 9.1 Проверки безопасности
- [ ] Проверка что Steam ID принадлежит авторизованному пользователю
- [ ] Защита от подделки asset_id
- [ ] Rate limiting для запросов к Steam API
- [ ] Логирование всех операций с инвентарем

#### 9.2 Обработка ошибок
- [ ] Приватный инвентарь - уведомление пользователя
- [ ] Steam API недоступен - fallback
- [ ] Предмет уже продан - обновление статуса

### 10. Тестирование (1 день)

#### 10.1 Unit тесты
- [ ] Тесты для SteamInventoryService
- [ ] Тесты для InspectUrlService
- [ ] Тесты создания лотов

#### 10.2 Интеграционные тесты
- [ ] Полный flow: авторизация → инвентарь → создание лота
- [ ] Тесты с приватным инвентарем
- [ ] Тесты обработки ошибок Steam API

## Дополнительные возможности (можно отложить)

### Расширенный функционал
- [ ] Массовое выставление предметов
- [ ] Автоматическое обновление цен по Steam Market
- [ ] Уведомления о изменении инвентаря
- [ ] История операций с предметами

### Интеграция с ботами
- [ ] Автоматическое создание trade offer при покупке
- [ ] Отслеживание статуса trade offer
- [ ] Автоматическое обновление статуса лота

## Технические детали

### Steam API endpoints
```
Инвентарь: https://steamcommunity.com/inventory/{steamid}/730/2
Профиль: https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/
```

### Структура данных Steam предмета
```json
{
  "assetid": "123456789",
  "classid": "987654321", 
  "instanceid": "0",
  "amount": "1",
  "pos": 1,
  "market_hash_name": "AK-47 | Redline (Field-Tested)",
  "market_tradable_restriction": 7,
  "fraudwarnings": [],
  "descriptions": [...]
}
```

### Приоритеты
1. **Критично**: Steam авторизация и получение инвентаря
2. **Высокий**: Создание лотов из Steam предметов  
3. **Средний**: Генерация inspect_url
4. **Низкий**: Расширенный функционал

## ✅ ТЕКУЩЕЕ СОСТОЯНИЕ (10.07.2025)

### Что уже реализовано:
1. **✅ Steam авторизация** - полностью работает (AuthController)
2. **✅ База данных** - все Steam поля в таблице `clients`
3. **✅ Конфигурация** - Steam API ключ настроен
4. **✅ SteamInventoryService** - получение инвентаря из Steam API
5. **✅ Миграция** - создана таблица `client_inventory_items` для кеша
6. **✅ Модели** - ClientInventoryItem с полным функционалом
7. **✅ InventoryController** - контроллер с методами управления инвентарем
8. **✅ Команда inventory:sync** - синхронизация инвентаря через Artisan
9. **✅ Роуты** - добавлены маршруты для инвентаря (/inventory, /inventory/sync)
10. **✅ Vue компоненты** - Profile.vue и InventoryGrid.vue для отображения инвентаря
11. **✅ Система уведомлений** - vue-toastification для уведомлений пользователя
12. **✅ Синхронизация инвентаря** - кнопка с кулдауном 2 минуты
13. **✅ Новая архитектура БД** - хранение полных данных Steam API
14. **✅ Миграция inventory v2** - расширенная структура таблицы с tags и descriptions
15. **✅ Обработка всех типов предметов** - оружие, граффити, стикеры, монеты

### Что было сделано сегодня:
1. **✅ Исправлена система уведомлений** - убрана дублированная система, оставлена vue-toastification
2. **✅ Исправлена проблема с пустым инвентарем** - добавлена обработка wear states при поиске предметов
3. **✅ Полностью переработана система инвентаря**:
   - Теперь сохраняются ВСЕ предметы из Steam, даже если их нет в справочнике
   - Добавлены поля: tags, descriptions, type, amount
   - Убран срок жизни кеша (expires_at)
4. **✅ Добавлен кулдаун на синхронизацию** - не чаще 1 раза в 2 минуты
5. **✅ Убрана пагинация** из инвентаря по просьбе пользователя
6. **✅ Исправлено отображение иконок** - добавлен правильный URL Steam

### Что нужно сделать дальше:
1. **🔄 Создание лотов** - возможность выставить предмет на продажу
2. **🔄 Trade URL** - настройка и валидация в профиле
3. **🔄 Генерация inspect_url** - для просмотра предмета в игре
4. **🔄 Проверка владения** - валидация при создании лота
5. **🔄 Рекомендуемые цены** - интеграция с Steam Market API

### Архитектура системы:
```
Steam API → SteamInventoryService → ClientInventoryItem (кеш) → InventoryController → Blade Views
```

### Логика работы:
1. При первом входе в инвентарь - автоматическая загрузка
2. Кеш инвентаря без срока жизни (обновляется только по кнопке)
3. Кнопка "Обновить" с кулдауном 2 минуты
4. Сохранение ВСЕХ предметов из Steam (включая граффити, стикеры, монеты)
5. Опциональная связь со справочником предметов через item_id

## Вопросы для обсуждения
1. Нужно ли разрешать продажу без привязки Steam? (только загруженные админом предметы)
2. Какой лимит запросов к Steam API установить?
3. Как часто обновлять кеш инвентаря?
4. Нужна ли возможность продажи предметов, которых уже нет в инвентаре?