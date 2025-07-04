# Структура базы данных CS2 Marketplace

## 1. **users** (только для админов Filament)
Стандартная таблица Laravel для администраторов панели управления.

## 2. **clients** (пользователи маркетплейса)
```sql
- id
- name (string)
- email (string, unique, nullable)
- steam_id (string, unique) - Steam ID
- steam_avatar (string, nullable) - аватар из Steam
- steam_trade_url (string, nullable) - Trade URL
- balance (decimal, default: 0) - баланс пользователя
- payment_password (string, nullable) - платежный пароль
- is_verified (boolean, default: false) - верификация для вывода
- is_bot (boolean, default: false) - флаг для ботов
- locale (enum: 'ru', 'en', default: 'ru') - язык интерфейса
- remember_token (string, nullable)
- created_at, updated_at
```

## 3. **items** (каталог всех предметов CS2)
```sql
- id
- steam_market_hash_name (string, unique) - уникальное имя из Steam
- name_ru (string) - название на русском
- name_en (string) - название на английском
- type (enum) - пистолеты, ножи, перчатки и т.д.
- rarity (enum) - Ширпотреб, Промышленное, Армейское и т.д.
- image_url (string) - ссылка на изображение
- min_steam_price (decimal, nullable) - мин. цена на Steam
- steam_listings_count (integer) - кол-во лотов на Steam
- is_valid (boolean) - валидность для быстрой продажи
- created_at, updated_at
```

## 4. **listings** (активные предложения на маркете)
```sql
- id
- client_id (foreign to clients) - продавец
- item_id (foreign) - предмет
- price (decimal) - цена продажи
- status (enum: 'active', 'sold', 'cancelled', 'expired')
- type (enum: 'p2p', 'bot_sale') - тип продажи
- float_value (decimal, nullable) - износ
- pattern_index (integer, nullable) - паттерн
- created_at, updated_at
```

## 5. **trades** (обмены)
```sql
- id
- listing_id (foreign) - связь с предложением
- buyer_id (foreign to clients) - покупатель
- seller_id (foreign to clients) - продавец
- steam_trade_id (string, nullable) - ID трейда в Steam
- status (enum: 'pending', 'confirmed', 'cancelled', 'timeout')
- price (decimal) - финальная цена
- platform_fee (decimal) - комиссия платформы
- created_at, updated_at
- confirmed_at (timestamp, nullable)
```

## 6. **transactions** (финансовые операции)
```sql
- id
- client_id (foreign to clients)
- type (enum: 'deposit', 'withdrawal', 'purchase', 'sale', 'fee')
- amount (decimal)
- balance_before (decimal)
- balance_after (decimal)
- status (enum: 'pending', 'completed', 'failed')
- payment_method (string, nullable)
- external_id (string, nullable) - ID во внешней платежной системе
- metadata (json) - дополнительные данные
- created_at, updated_at
```

## 7. **bot_inventories** (инвентари ботов)
```sql
- id
- bot_id (foreign to clients) - ID бота
- item_id (foreign) - предмет
- steam_asset_id (string) - ID в Steam инвентаре
- float_value (decimal, nullable)
- pattern_index (integer, nullable)
- acquired_price (decimal) - цена приобретения
- status (enum: 'available', 'reserved', 'sold')
- created_at, updated_at
```

## 8. **price_history** (история цен)
```sql
- id
- item_id (foreign)
- source (enum: 'steam', 'internal', 'bot_sale')
- price (decimal)
- created_at
```

## 9. **settings** (настройки системы)
```sql
- key (string, unique)
- value (text)
- type (enum: 'string', 'integer', 'decimal', 'boolean', 'json')
- description (text)
```

## 10. **client_inventory_cache** (кэш инвентаря пользователей)
```sql
- id
- client_id (foreign to clients)
- steam_data (json) - данные инвентаря
- expires_at (timestamp)
- created_at, updated_at
```

## Дополнительные соображения:

### Индексы
- users: steam_id, is_bot
- items: steam_market_hash_name, type, rarity, is_valid
- listings: user_id, item_id, status, type, created_at
- trades: buyer_id, seller_id, status, created_at
- transactions: user_id, type, status, created_at
- bot_inventories: bot_id, item_id, status
- price_history: item_id, source, created_at

### Полнотекстовый поиск
- Для name_ru и name_en в таблице items

### Партиционирование
- price_history по дате для оптимизации больших объемов данных

### Soft deletes
- Возможно применить для listings и trades для сохранения истории

### Связи между таблицами
1. clients -> listings (один ко многим)
2. clients -> trades (как buyer и seller)
3. clients -> transactions (один ко многим)
4. clients -> bot_inventories (для ботов)
5. clients -> client_inventory_cache (один ко многим)
6. items -> listings (один ко многим)
7. items -> bot_inventories (один ко многим)
8. items -> price_history (один ко многим)
9. listings -> trades (один к одному)

### Триггеры и события
1. При создании trade - резервирование предмета
2. При подтверждении trade - обновление балансов через transactions
3. При таймауте trade - освобождение предмета
4. Автоматическое обновление price_history

### Безопасность
1. Шифрование payment_password
2. Логирование всех финансовых операций
3. Проверка целостности данных при транзакциях
4. Rate limiting для API endpoints