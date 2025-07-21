# 📦 Упаковка браузерного расширения

## Артisan команды для упаковки

### Основная команда упаковки

```bash
php artisan extension:pack [опции]
```

#### Опции

- `--browser=BROWSER` - Целевой браузер: `chrome`, `firefox`, `all` (по умолчанию: `chrome`)
- `--output=PATH` - Выходная директория (по умолчанию: `storage/app/extensions`)
- `--ext-version=VERSION` - Версия расширения (например: `1.2.0`)
- `--clean` - Очистить выходную директорию перед упаковкой

#### Примеры использования

```bash
# Упаковать для Chrome (базовая команда)
php artisan extension:pack

# Упаковать для всех браузеров с новой версией
php artisan extension:pack --browser=all --ext-version=1.2.0

# Очистить и упаковать в указанную директорию
php artisan extension:pack --clean --output=/tmp/extensions

# Упаковать только для Firefox
php artisan extension:pack --browser=firefox
```

### Просмотр упакованных расширений

```bash
php artisan extension:list [опции]
```

#### Опции

- `--path=PATH` - Путь к директории с архивами (по умолчанию: `storage/app/extensions`)

#### Примеры

```bash
# Показать все архивы в стандартной директории
php artisan extension:list

# Показать архивы в другой директории
php artisan extension:list --path=/tmp/extensions
```

## 🔧 Что делает команда упаковки

### Для Chrome (Manifest v3)
1. **Копирует** все файлы расширения во временную директорию
2. **Обновляет версию** в `manifest.json` если указана
3. **Добавляет Chrome-специфичные** настройки
4. **Создает ZIP архив** с именем `cs2-marketplace-extension-chrome-vX.X.X.zip`
5. **Исключает** ненужные файлы (`.git`, `node_modules`, etc.)

### Для Firefox (Manifest v2)
1. **Конвертирует** Manifest v3 в Manifest v2
2. **Заменяет** Service Worker на background script
3. **Создает адаптер** для Firefox API
4. **Добавляет** `browser_specific_settings` для Firefox
5. **Конвертирует** `action` в `browser_action`

## 📁 Структура выходных файлов

```
storage/app/extensions/
├── cs2-marketplace-extension-chrome-v1.0.0.zip
├── cs2-marketplace-extension-chrome-v1.1.0.zip
├── cs2-marketplace-extension-firefox-v1.1.0.zip
└── ...
```

## 🚀 Автоматизация сборки

### Пример script для CI/CD

```bash
#!/bin/bash
# build-extensions.sh

echo "🚀 Автоматическая сборка расширений..."

# Очищаем старые архивы
php artisan extension:pack --clean --browser=all --ext-version=$(date +%Y.%m.%d)

# Показываем результат
php artisan extension:list

echo "✅ Сборка завершена!"
```

### Версионирование

Команда поддерживает семантическое версионирование:
- `--ext-version=1.0.0` - Мажорная версия
- `--ext-version=1.1.0` - Минорная версия  
- `--ext-version=1.0.1` - Патч версия

## 🔍 Отладка

### Проверка содержимого архива

```bash
# Распаковать архив для проверки
unzip -l storage/app/extensions/cs2-marketplace-extension-chrome-v1.0.0.zip

# Или извлечь в папку
unzip storage/app/extensions/cs2-marketplace-extension-chrome-v1.0.0.zip -d /tmp/check-extension
```

### Логи команды

Команда выводит подробную информацию о процессе:
- ✅ Успешные операции
- ❌ Ошибки
- 📁 Создание директорий
- 📦 Размеры архивов

## 🚨 Исключаемые файлы

При упаковке автоматически исключаются:
- `.git/` - Git репозиторий
- `.DS_Store` - macOS системные файлы
- `Thumbs.db` - Windows системные файлы
- `*.log` - Лог файлы
- `*.tmp` - Временные файлы
- `node_modules/` - Node.js зависимости
- `.env` - Файлы окружения
- `*.zip` - Существующие архивы

## 📝 Требования

### Системные требования
- **PHP** >= 8.2
- **Laravel** >= 11.0
- **ZipArchive** PHP extension

### Структура проекта
Команда ожидает папку `browser-extension/` в корне проекта со следующей структурой:
```
browser-extension/
├── manifest.json
├── background/
├── content/
├── popup/
├── utils/
└── assets/
```

## 🛠️ Расширение команды

Код команды находится в `app/Console/Commands/PackExtensionCommand.php` и может быть расширен для:

- Добавления новых браузеров
- Кастомной обработки файлов
- Интеграции с CI/CD
- Автоматической загрузки в магазины расширений

## 📞 Поддержка

При проблемах с упаковкой:
1. Проверьте права доступа к директориям
2. Убедитесь что установлен ZipArchive
3. Проверьте синтаксис `manifest.json`
4. Запустите команду с флагом `-v` для подробного вывода