# CS-SKINS.pro Trading Assistant - Browser Extension

Браузерное расширение для автоматизации Steam трейдов на платформе CS-SKINS.pro с поддержкой WebSocket соединений в реальном времени.

## 🚀 Быстрый старт

### Установка для разработки

1. **Клонируйте или скопируйте папку расширения**
   ```bash
   cd browser-extension/
   ```

2. **Загрузите в Chrome как unpacked extension**
   - Откройте Chrome и перейдите в `chrome://extensions/`
   - Включите "Developer mode" (Режим разработчика)
   - Нажмите "Load unpacked" (Загрузить распакованное)
   - Выберите папку `browser-extension/`

3. **Настройте расширение**
   - Кликните на иконку расширения в панели браузера
   - Войдите в свой профиль на CS-SKINS.pro
   - Найдите "Токен расширения" в информации профиля
   - Вставьте токен в popup расширения и нажмите "Подключить"

## 📁 Структура проекта

```
browser-extension/
├── manifest.json                 # Манифест расширения (Manifest v3)
├── index.html                   # Главная страница popup/detached окна
├── assets/
│   ├── js/
│   │   ├── service-worker.js    # Background service worker
│   │   ├── service-worker.min.js
│   │   ├── index.js            # Popup interface логика
│   │   ├── index.min.js
│   │   ├── steam-injector.js   # Content script для Steam
│   │   └── steam-injector.min.js
│   ├── css/
│   │   ├── index.css           # Стили интерфейса
│   │   └── index.min.css
│   └── icons/                  # Иконки расширения (16x16 до 128x128)
│       ├── icon-*.png          # Обычные иконки
│       ├── icon-*-off.png      # Иконки неактивного состояния
│       └── icon-active-*.png   # Иконки активного состояния
├── README.md                    # Документация
└── PACKAGING.md                # Инструкции по сборке
```

## 🔧 Технические детали

### Manifest v3 (Chrome/Edge)
- **Service Worker** вместо background scripts
- **Dynamic imports** для модульности
- **Content scripts** для интеграции с Steam
- **Host permissions** для доступа к Steam и платформе

### API Endpoints
Расширение использует только 2 HTTP API эндпоинта:

- `POST /api/ext-api/auth` - Авторизация расширения (проверка токена)
- `GET /api/ext-api/user` - Получение информации о пользователе

**Примечание**: Все остальные данные (заказы, статистика, обновления статусов) передаются через WebSocket в реальном времени.

### WebSocket Integration (основной канал данных)
- **Сервер**: Laravel Reverb WebSocket сервер
- **URL**: `wss://cs-skins.s1temaker.ru/ws/app/cs-skins-key`
- **Протокол**: Pusher-совместимый (protocol=7, version=8.0.1)
- **Каналы**: `seller-{user_id}-{hash}` для получения событий продавца (с хешем токена для безопасности)
- **События**: 
  - `trade_offer_created` - новое торговое предложение создано (группа товаров)
  - `stats` - обновление статистики продавца
  - `force_logout` - принудительный выход (токен изменен)
- **Heartbeat**: ping каждые 25 секунд для поддержания соединения

### Безопасность
- **Bearer токены** для авторизации
- **Rate limiting** на уровне API
- **CORS** настройки для безопасности
- **CSP** (Content Security Policy)

## 🎯 Основной функционал

### 1. Авторизация и подключение
- Связка расширения с аккаунтом через Bearer токен
- WebSocket подключение для real-time событий
- Автоматическое переподключение при разрыве соединения
- Heartbeat каждые 25 секунд для поддержания соединения

### 2. Мониторинг заказов
- Real-time уведомления через WebSocket
- Подписка на персональный канал продавца
- Обработка событий трейдов в реальном времени
- Логирование всех операций (до 100 записей)

### 3. Автоматизация Steam трейдов
- Извлечение Steam session данных (sessionId, steamId, CSRF)
- Создание Trade Offers через Steam Web API
- Конвертация Steam ID64 в Account ID
- Обработка Trade URLs и токенов

### 4. Пользовательский интерфейс
- Три состояния: Не подключен / Подключен / Активен
- Статистика трейдов за день (активные, завершенные, отмененные)
- Последние 5 событий в режиме реального времени
- Detached window режим для работы в отдельном окне
- Индикаторы статуса в иконке расширения

## 🔄 Жизненный цикл заказа

```
1. Покупатель оформляет заказ на сайте
   ↓
2. Сервер группирует товары по продавцам в TradeOffer
   ↓
3. WebSocket событие 'trade_offer_created' → расширение
   ↓
4. Content script извлекает Steam session данные
   ↓
5. Создание Trade Offer для группы товаров через Steam Web API
   ↓
6. WebSocket событие 'trade_offer_sent' → сервер (через sendToServer)
   ↓
7. Покупатель принимает трейд в Steam
   ↓
7. WebSocket событие 'trade_completed' → завершение
```

## 🛠️ Разработка

### Отладка
- **Service Worker**: `chrome://extensions/` → Детали → Проверить страницы: service worker
- **Content Script**: DevTools на страницах Steam (объект `window.steamInjector`)
- **Popup**: DevTools в popup окне (Ctrl+Shift+I)
- **WebSocket**: Сообщения видны в Network tab DevTools

### Компоненты для отладки
```javascript
// В Service Worker
console.log('Trading Assistant:', tradingAssistant);

// В Content Script (Steam)
console.log('Steam Injector:', window.steamInjector);

// В Popup
console.log('Popup Interface:', popupInterface);
```

### Тестирование
1. Авторизуйтесь через токен в popup
2. Убедитесь что WebSocket подключение активно (зеленый индикатор)
3. Создайте тестовый заказ на платформе
4. Проверьте получение события в логах расширения
5. Откройте Steam и проверьте создание Trade Offer

### Артisan команды для сборки
```bash
# Сборка для Chrome
php artisan extension:pack --browser=chrome --ext-version=1.0.0

# Сборка для всех браузеров
php artisan extension:pack --browser=all --clean

# Просмотр собранных пакетов
php artisan extension:list
```

## 📱 Поддержка браузеров

### ✅ Поддерживается
- **Chrome** (Manifest v3)
- **Edge** (Chromium)

### 🔄 В планах
- **Firefox** (адаптация Manifest v2)
- **Safari** (будущие версии)

## 🔋 Состояния расширения

### Индикаторы в иконке
- **🔴 ×** - Не подключен (нет токена)
- **🟡 ○** - Подключен (есть токен, но WebSocket неактивен)
- **🟢 ●** - Активен (WebSocket соединение работает)

### Статусы подключения
- **Не подключен** - Требуется авторизация через токен
- **Подключен** - Токен валиден, но расширение на паузе
- **Активен** - WebSocket работает, обрабатываются события

## 🚨 Известные ограничения

1. **Steam Guard Mobile**: Требуется подтверждение трейдов в мобильном приложении Steam
2. **Rate Limits**: Ограничения Steam API на количество одновременных трейдов
3. **Session Timeout**: Необходимость периодической переавторизации в Steam
4. **WebSocket Reconnection**: Автоматическое переподключение через 5 секунд при разрыве
5. **Cross-Origin**: Работает только на доменах steamcommunity.com и cs-skins.s1temaker.ru

## 🎛️ Дополнительные функции

### Detached Window
- Кнопка "Открыть в отдельном окне" для работы в отдельном popup
- Автоматическое обновление заголовка окна в зависимости от статуса
- Независимая работа от основного браузера

### Управление
- **Запустить/Остановить** - управление WebSocket соединением
- **Обновить** - перезагрузка статуса и статистики
- **Профиль** - быстрый переход на страницу профиля
- **Отключить** - выход из аккаунта и очистка данных

### Уведомления
- Браузерные уведомления о важных событиях
- Внутренние уведомления в popup интерфейсе
- Автоматическое скрытие через 3 секунды

## 📋 TODO

### Высокий приоритет
- [ ] Обработка Steam Guard mobile confirmations
- [ ] Retry логика для неуспешных Trade Offers
- [ ] Расширенная диагностика WebSocket соединений

### Средний приоритет
- [ ] Firefox адаптация (конвертация в Manifest v2)
- [ ] Настройки пользователя (звуки, интервалы обновления)
- [ ] Экспорт статистики и логов

### Низкий приоритет
- [ ] Темная/светлая тема интерфейса
- [ ] Звуковые уведомления для событий
- [ ] Графики и детальная аналитика

## 🐛 Troubleshooting

### Расширение не подключается
1. **Проверьте токен**: Скопируйте новый токен из профиля CS-SKINS.pro
2. **WebSocket блокировка**: Отключите блокировщики рекламы или VPN
3. **Права доступа**: Убедитесь что расширение имеет права на cs-skins.s1temaker.ru
4. **Перезагрузка**: Попробуйте перезагрузить расширение в chrome://extensions/

### WebSocket не подключается
1. **Проверьте статус в popup**: Должен быть "Активен" (зеленый)
2. **Network tab**: Проверьте WebSocket соединения в DevTools
3. **Heartbeat**: Убедитесь что пинги отправляются каждые 25 секунд
4. **Переподключение**: Автоматически происходит через 5 секунд при разрыве

### Трейды не создаются
1. **Steam авторизация**: Войдите в Steam в том же браузере
2. **Trade URL**: Проверьте корректность Trade URL покупателя
3. **Steam session**: Обновите страницу Steam для получения новой сессии
4. **Content script**: Убедитесь что скрипт загружен на странице Steam

### Диагностика через DevTools
```javascript
// Проверка WebSocket в Service Worker
chrome.runtime.getBackgroundPage((bg) => {
  console.log('WebSocket:', bg.tradingAssistant.wsConnection);
  console.log('Is Active:', bg.tradingAssistant.isActive);
});

// Проверка Steam session в Content Script
console.log('Steam Session:', window.steamInjector.getSteamSessionData());
```

## 📞 Поддержка

### Получение помощи
- **Логи расширения**: Проверьте "Последние события" в popup
- **DevTools**: Откройте консоль Service Worker для детальной диагностики
- **Техподдержка**: Обратитесь к администратору CS-SKINS.pro
- **Проблемы**: Создайте issue с подробным описанием и логами

### Полезные команды для диагностики
```bash
# Сборка debug версии
php artisan extension:pack --browser=chrome --ext-version=debug

# Просмотр логов Laravel
tail -f storage/logs/laravel.log | grep "ext-api"

# Проверка WebSocket сервера
php artisan reverb:start --debug
```

## 🔧 Системные требования

### Браузер
- **Chrome** 88+ (Manifest v3 поддержка)
- **Edge** 88+ (Chromium база)

### Разрешения
- `storage` - сохранение токенов и логов
- `notifications` - уведомления о событиях
- `activeTab` - доступ к активным вкладкам
- `windows` - создание detached окон
- `alarms` - keep-alive система

### Домены
- `https://steamcommunity.com/*` - Steam integration
- `https://cs-skins.s1temaker.ru/*` - API и WebSocket
- `https://cs-skins.local/*` - для разработки

## 📄 Лицензия

Этот проект является частью CS-SKINS.pro торговой платформы.
Использование разрешено только авторизованным пользователям платформы.