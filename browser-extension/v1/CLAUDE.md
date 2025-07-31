# Claude Instructions для CS-SKINS Browser Extension

## Архитектура расширения

### Файловая структура
```
browser-extension/
├── assets/js/
│   ├── service-worker.js     # Background script (главный)
│   ├── steam-injector.js     # Content script для Steam
│   └── index.js             # Popup interface
├── manifest.json
└── CLAUDE.md               # Этот файл
```

## Принципы обработки сообщений и ошибок

### 1. Централизованная обработка Steam API (SteamAPI класс)

**service-worker.js содержит класс SteamAPI** для всех запросов к Steam:
- Все Steam запросы идут через `SteamAPI.request(config)`
- HTTP 200 + валидные данные → передается в обработчик
- Все остальные ответы → отправляются на сервер как `steam_api_error`

**Пример использования:**
```javascript
// В steam-injector.js
chrome.runtime.sendMessage({
    type: 'STEAM_API_REQUEST',
    config: {
        url: 'https://steamcommunity.com/tradeoffer/new/send',
        method: 'POST',
        data: tradeData,
        preRequestUrl: order.buyer.trade_url, // Опционально
        operation: 'sendTradeOffer',
        successValidator: function(result) {
            return result && result.tradeofferid && !result.strError;
        }
    }
});
```

### 2. Логирование событий

**ВАЖНО: Только сервер добавляет записи в лог событий**

- ❌ Расширение НЕ добавляет локальные записи через `addLogEntry()`
- ✅ Все события отправляются на сервер через WebSocket
- ✅ Сервер отправляет обратно сообщения с `log_message` для отображения в UI

**Текущие обработчики событий от сервера:**
```javascript
case 'stats':
    await tradingAssistant.storage.addLogEntry('info', messageData.log_message);
case 'warning':
    await tradingAssistant.storage.addLogEntry('warning', messageData.log_message);
case 'trade_offer_sent':
    await tradingAssistant.storage.addLogEntry('success', messageData.log_message);
case 'trade_offer_cancelled':
    await tradingAssistant.storage.addLogEntry('success', messageData.log_message);
```

### 3. Обработка ошибок Steam API

**Автоматическая отправка ошибок на сервер:**
```javascript
// В SteamAPI.logError()
await sendToServer('steam_api_error', {
    operation: config.operation,
    url: config.url,
    httpStatus: response.status,
    error: errorMessage,
    rawResponse: responseText,
    extension_version: chrome.runtime.getManifest().version
});
```

**Сервер обрабатывает в WebSocketServiceProvider.php:**
```php
case 'steam_api_error':
    $this->handleSteamApiError($sellerId, $messageData);
```

### 4. Структура сообщений для сервера

**Успешное создание трейда:**
```javascript
await sendToServer('trade_offer_sent', {
    trade_offer_id: tradeOffer.trade_offer_id,
    steam_trade_offer_id: steamTradeOfferId
});
```

**Ошибка создания трейда:**
```javascript
await sendToServer('trade_offer_failed', {
    trade_offer_id: tradeOffer.trade_offer_id,
    error: errorMessage
});
```

**Отмена Steam трейда:**
```javascript
await sendToServer('steam_trade_cancelled', {
    trade_offer_id: trade_offer_id,
    success: true/false,
    error: errorMessage // при success: false
});
```

**Запрос статистики:**
```javascript
await sendToServer('stats_request', {});
```

### 5. Принципы разработки

**ВАЖНО: Система разрабатывается с нуля для MVP**
- ❌ **Никаких фалбеков** не нужно - система новая
- ✅ **Модифицировать существующие методы** вместо создания новых
- ✅ **Дополнять функциональность** без потери основной работы
- ✅ **Простота над совместимостью** - "не надо усложнять"

### 6. Правила для новых функций

1. **Все Steam API запросы** → только через `SteamAPI.request()`
2. **Все логирование** → только через сервер (WebSocket)
3. **Ошибки Steam** → автоматически отправляются на сервер
4. **UI обновления** → только на основе сообщений от сервера
5. **Нет локальных уведомлений** → только серверные сообщения
6. **Модификация вместо добавления** → улучшать существующие методы
7. **Нет фалбеков** → только новая логика работы
8. **Комментарии только функциональные** → никаких заметок типа "уже сохранена", "потом добавим"

### 7. WebSocket сообщения (service-worker.js)

**Отправка на сервер:**
```javascript
async function sendToServer(messageType, data) {
    // Отправка через WebSocket channel
}
```

**Получение от сервера:**
```javascript
// В onmessage обработчике
const messageData = JSON.parse(event.data);
switch(messageData.event) {
    case 'stats':
    case 'warning': 
    case 'trade_offer_sent':
    case 'trade_offer_cancelled':
    // Только эти события добавляют записи в лог
}
```

### 8. Именование функций

- ✅ `createTradeOffer()`
- ✅ `cancelTradeOffer()`
- ✅ `SteamAPI.request()`

### 9. Тестирование

При модификации функций:
1. Проверить что ошибки отправляются на сервер
2. Проверить что нет дублирования логов
3. Проверить что UI обновляется от серверных сообщений
4. Проверить работу через централизованный SteamAPI
5. **Убедиться что основная функциональность не сломана**
