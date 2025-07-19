# TODO: Система тегов для маркетплейса

## Проблема
Текущая система хранения тегов в JSON (`inventory_tags`) неэффективна:
- Медленный парсинг JSON на каждом запросе
- Сложные фильтры через `JSON_SEARCH`
- Невозможность создания эффективных индексов
- Проблемы масштабирования при росте до 100k+ предметов

## Решение: Гибридный подход

### Основные принципы:
1. **Стабильные основные теги** → денормализация (прямые ссылки)
2. **Изменяемые специфичные теги** → нормализация (отдельная таблица)
3. **Парсинг при получении инвентаря** → структурированное хранение

### Частота изменений Steam тегов:
- **Основные теги** (type, quality, exterior, rarity) - очень стабильны, меняются раз в годы
- **Специфичные теги** (tournament, team, collection) - меняются регулярно

---

## Структура базы данных

### 1. Справочники тегов
```sql
-- Категории тегов
CREATE TABLE tag_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,       -- type, quality, rarity, exterior, tournament, team
    steam_category VARCHAR(50) NOT NULL,    -- Type, Quality, Rarity, Exterior, Tournament, TournamentTeam
    is_primary BOOLEAN DEFAULT FALSE,       -- основной тег (для денормализации)
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Значения тегов
CREATE TABLE tags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    steam_internal_name VARCHAR(100) NOT NULL,  -- CSGO_Type_Pistol, normal, Tournament22
    normalized_value VARCHAR(50) NOT NULL,      -- pistol, normal, copenhagen2024
    color VARCHAR(7) NULL,                      -- HEX цвет из Steam
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES tag_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tag (category_id, steam_internal_name),
    INDEX idx_category (category_id),
    INDEX idx_normalized (normalized_value)
);
```

### 2. Основные теги (денормализация)
```sql
-- Прямые ссылки на стабильные теги
ALTER TABLE inventory_items ADD COLUMN type_id BIGINT UNSIGNED;
ALTER TABLE inventory_items ADD COLUMN quality_id BIGINT UNSIGNED;
ALTER TABLE inventory_items ADD COLUMN rarity_id BIGINT UNSIGNED;
ALTER TABLE inventory_items ADD COLUMN exterior_id BIGINT UNSIGNED;

ALTER TABLE listings ADD COLUMN type_id BIGINT UNSIGNED;
ALTER TABLE listings ADD COLUMN quality_id BIGINT UNSIGNED;
ALTER TABLE listings ADD COLUMN rarity_id BIGINT UNSIGNED;
ALTER TABLE listings ADD COLUMN exterior_id BIGINT UNSIGNED;

-- Индексы для быстрой фильтрации
CREATE INDEX idx_inventory_type ON inventory_items(type_id);
CREATE INDEX idx_inventory_quality ON inventory_items(quality_id);
CREATE INDEX idx_inventory_rarity ON inventory_items(rarity_id);
CREATE INDEX idx_inventory_exterior ON inventory_items(exterior_id);

CREATE INDEX idx_listings_type ON listings(type_id);
CREATE INDEX idx_listings_quality ON listings(quality_id);
CREATE INDEX idx_listings_rarity ON listings(rarity_id);
CREATE INDEX idx_listings_exterior ON listings(exterior_id);

-- Внешние ключи
ALTER TABLE inventory_items ADD FOREIGN KEY (type_id) REFERENCES tags(id);
ALTER TABLE inventory_items ADD FOREIGN KEY (quality_id) REFERENCES tags(id);
ALTER TABLE inventory_items ADD FOREIGN KEY (rarity_id) REFERENCES tags(id);
ALTER TABLE inventory_items ADD FOREIGN KEY (exterior_id) REFERENCES tags(id);

ALTER TABLE listings ADD FOREIGN KEY (type_id) REFERENCES tags(id);
ALTER TABLE listings ADD FOREIGN KEY (quality_id) REFERENCES tags(id);
ALTER TABLE listings ADD FOREIGN KEY (rarity_id) REFERENCES tags(id);
ALTER TABLE listings ADD FOREIGN KEY (exterior_id) REFERENCES tags(id);
```

### 3. Специфичные теги (нормализация)
```sql
-- Связи с изменяемыми тегами
CREATE TABLE item_tags (
    item_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('inventory', 'listing') NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (item_id, item_type, tag_id),
    INDEX idx_item (item_id, item_type),
    INDEX idx_tag (tag_id)
);
```

---

## План реализации

### Этап 1: Создание таблиц и справочников
- [x] Создать миграции для новых таблиц
- [x] Заполнить базовые категории тегов (type, quality, rarity, exterior) в миграции

### Этап 2: Парсинг инвентаря
- [x] Обновить `InventoryController` для парсинга Steam tags
- [x] Создать метод `parseAndSaveTags()` для извлечения тегов из JSON
- [x] Добавить автоматическое создание новых тегов при их появлении
- [x] Обновить логику синхронизации инвентаря

### Этап 3: Обновление листингов
- [x] Модифицировать создание листингов для копирования тегов
- [x] Обновить `InventoryController::createListing()` 
- [x] Добавить копирование основных тегов (type_id, quality_id, etc.)
- [x] Добавить копирование специфичных тегов через `item_tags`

### Этап 4: Рефакторинг маркетплейса
- [x] Обновить `MarketplaceController::getListings()` для новых индексов
- [x] Переписать `MarketplaceController::getCategories()` без JSON парсинга
- [x] Обновить `MarketplaceController::getTags()` для быстрых подсчетов
- [x] Добавить кэширование результатов фильтров

### Этап 5: Обновление фронтенда
- [x] Обновить `Marketplace.vue` для новых API endpoint'ов
- [x] Обновить переводы тегов в `lang/ru/tags.php`
- [x] Протестировать фильтрацию и производительность

### Этап 6: Компоненты профиля
- [x] Оптимизировать `Inventory.vue` - убрать дублирование кода
- [x] Обновить `Inventory.vue` для использования structured_tags
- [x] Рефакторинг `Trading.vue` - использовать v-for с computed property
- [x] Добавить счетчики предметов в табы инвентаря
- [x] Исправить поведение UI при выставлении предметов на продажу


## Переводы

### Файл `lang/ru/tags.php`:
```php
return [
    'categories' => [
        'type' => 'Тип',
        'quality' => 'Качество',
        'rarity' => 'Редкость',
        'exterior' => 'Внешний вид',
        'tournament' => 'Турнир',
        'team' => 'Команда',
        'collection' => 'Коллекция',
    ],
    'values' => [
        // Типы
        'rifle' => 'Винтовка',
        'pistol' => 'Пистолет',
        'knife' => 'Нож',
        'sticker' => 'Наклейка',
        
        // Качество
        'normal' => 'Обычный',
        'stattrak' => 'StatTrak™',
        'souvenir' => 'Сувенир',
        
        // Внешний вид
        'fn' => 'Прямо с завода',
        'mw' => 'Немного поношенное',
        'ft' => 'После полевых испытаний',
        'ww' => 'Поношенное',
        'bs' => 'Закалённое в боях',
        
        // Редкость
        'consumer' => 'Ширпотреб',
        'industrial' => 'Промышленное качество',
        'milspec' => 'Армейское качество',
        'restricted' => 'Ограниченное',
        'classified' => 'Засекреченное',
        'covert' => 'Тайное',
        'contraband' => 'Контрабанда',
    ]
];
```
