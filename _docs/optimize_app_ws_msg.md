# Оптимизация WebSocket сообщений между расширением и сервером

## Анализ текущей проблемы

### Проблема
Расширение отправляет избыточный объем данных каждые 24 секунды:
- Полная Steam сессия (все cookies и токены)
- Все трейды продавца без фильтрации
- Обновление Steam страницы при каждом запросе
- Перегрузка WebSocket соединений

### Текущая структура данных
```javascript
// service-worker.js:56-67
const dataToSend = {
    session: session,           // Полная Steam сессия (избыточно)
    timestamp: new Date().toISOString(),
    trades: session.trades.map(trade => ({  // ВСЕ трейды (избыточно)
        trade_offer_id: trade.tradeofferid,
        status: trade.trade_offer_state
    }))
};
```

### Что реально нужно серверу
Анализ `TradeService.php` показал минимальные требования:
- `sessionid` - для всех Steam API запросов
- `steamLoginSecure` - для аутентификации  
- `steamid` - для проверки соответствия аккаунта

## Стратегия оптимизации

### Приоритет 1: Минимизация данных сессии

#### Оптимизированная структура сессии
```javascript
// Вместо полной сессии отправляем только необходимое
const minimalSession = {
    sessionid: session.sessionid,
    steamLoginSecure: session.steamLoginSecure,
    steamid: session.steamid
};

const dataToSend = {
    session: minimalSession,  // Только необходимые поля
    timestamp: new Date().toISOString()
};
```

### Приоритет 2: Эффективное кеширование трейдов (для больших объемов)

#### Алгоритм сравнения для 200+ трейдов
```javascript
class TradesCache {
    constructor() {
        this.lastTradesHash = localStorage.getItem('last_trades_hash') || '';
        this.lastTrades = JSON.parse(localStorage.getItem('last_trades') || '{}');
    }

    // Быстрое сравнение через хеш
    hasChanges(trades) {
        const currentHash = this.generateHash(trades);
        return currentHash !== this.lastTradesHash;
    }

    // O(n) сложность для генерации хеша
    generateHash(trades) {
        return trades
            .map(t => `${t.tradeofferid}:${t.trade_offer_state}`)
            .sort()
            .join('|');
    }

    // Находим только измененные трейды
    findChanges(trades) {
        const changes = {
            added: [],
            updated: [],
            removed: []
        };

        const currentTradesMap = new Map(trades.map(t => [t.tradeofferid, t]));
        const lastTradesMap = new Map(Object.entries(this.lastTrades));

        // Новые и обновленные
        for (const [id, trade] of currentTradesMap) {
            const lastTrade = lastTradesMap.get(id);
            if (!lastTrade) {
                changes.added.push(trade);
            } else if (lastTrade.trade_offer_state !== trade.trade_offer_state) {
                changes.updated.push(trade);
            }
        }

        // Удаленные
        for (const [id] of lastTradesMap) {
            if (!currentTradesMap.has(id)) {
                changes.removed.push(id);
            }
        }

        return changes;
    }

    updateCache(trades) {
        const hash = this.generateHash(trades);
        const tradesMap = Object.fromEntries(
            trades.map(t => [t.tradeofferid, t])
        );

        this.lastTradesHash = hash;
        this.lastTrades = tradesMap;
        
        localStorage.setItem('last_trades_hash', hash);
        localStorage.setItem('last_trades', JSON.stringify(tradesMap));
    }
}
```

#### Использование в service-worker
```javascript
const tradesCache = new TradesCache();

async function checkStatusWebsocket() {
    // ... получение сессии ...
    
    if (session && session.trades) {
        // Проверяем изменения трейдов
        if (tradesCache.hasChanges(session.trades)) {
            const changes = tradesCache.findChanges(session.trades);
            
            // Отправляем только изменения
            if (changes.added.length > 0 || changes.updated.length > 0 || changes.removed.length > 0) {
                const sent = await sendToServer('trades_update', {
                    session: minimalSession,
                    trades_changes: changes,
                    timestamp: new Date().toISOString()
                });
                
                if (sent) {
                    tradesCache.updateCache(session.trades);
                }
            }
        }
    }
    
    // Отправляем минимальную сессию для heartbeat
    const sent = await sendToServer('session_heartbeat', {
        session: minimalSession,
        timestamp: new Date().toISOString()
    });
}
```

### Приоритет 3: GZIP сжатие для больших массивов

#### Реализация сжатия
```javascript
// Подключаем pako для gzip в manifest.json
// "content_scripts": [{ "js": ["libs/pako.min.js", "service-worker.js"] }]

class MessageCompressor {
    static compress(data) {
        const jsonString = JSON.stringify(data);
        
        // Сжимаем только если данных много (>1KB)
        if (jsonString.length > 1024) {
            try {
                const compressed = pako.gzip(jsonString);
                return {
                    compressed: Array.from(compressed),
                    encoding: 'gzip',
                    original_size: jsonString.length,
                    compressed_size: compressed.length
                };
            } catch (error) {
                console.warn('Compression failed, sending uncompressed:', error);
                return { data, encoding: 'none' };
            }
        }
        
        return { data, encoding: 'none' };
    }
}

// Использование
async function sendToServer(type, data) {
    const message = {
        event: 'extension-message',
        data: { type, ...MessageCompressor.compress(data) },
        channel: storageData.websocketChannel
    };
    
    this.wsConnection.send(JSON.stringify(message));
}
```

#### Серверная обработка сжатых данных
```php
// WebSocketServiceProvider.php
private function handleClientMessage(int $sellerId, array $messageData, ?string $channel = null): void
{
    // Распаковываем сжатые данные
    if (isset($messageData['encoding']) && $messageData['encoding'] === 'gzip') {
        $compressed = $messageData['compressed'];
        $decompressed = gzuncompress(pack('C*', ...$compressed));
        $messageData = json_decode($decompressed, true);
    }
    
    $messageType = $messageData['type'] ?? null;
    // ... остальная обработка
}
```

### Приоритет 4: Батчинг изменений

#### Накопление изменений за период времени
```javascript
class ChangesBatcher {
    constructor() {
        this.pendingChanges = [];
        this.batchTimer = null;
        this.batchTimeout = 15000; // 15 секунд
        this.maxBatchSize = 50; // максимум изменений в батче
    }

    addChange(change) {
        this.pendingChanges.push({
            ...change,
            timestamp: new Date().toISOString()
        });

        // Отправляем батч если он заполнен
        if (this.pendingChanges.length >= this.maxBatchSize) {
            this.flushBatch();
            return;
        }

        // Или запускаем таймер
        if (!this.batchTimer) {
            this.batchTimer = setTimeout(() => {
                this.flushBatch();
            }, this.batchTimeout);
        }
    }

    async flushBatch() {
        if (this.pendingChanges.length === 0) return;

        const batch = [...this.pendingChanges];
        this.pendingChanges = [];
        
        if (this.batchTimer) {
            clearTimeout(this.batchTimer);
            this.batchTimer = null;
        }

        try {
            await sendToServer('trades_batch', {
                changes: batch,
                batch_size: batch.length,
                timestamp: new Date().toISOString()
            });
        } catch (error) {
            // При ошибке возвращаем изменения в очередь
            this.pendingChanges.unshift(...batch);
            console.error('Failed to send batch:', error);
        }
    }
}

const changesBatcher = new ChangesBatcher();

// Использование
function onTradeStatusChanged(trade) {
    changesBatcher.addChange({
        type: 'trade_status_update',
        trade_offer_id: trade.tradeofferid,
        old_status: trade.old_status,
        new_status: trade.trade_offer_state
    });
}
```

### Приоритет 5: Дифференциальные обновления с гарантией доставки

#### Система версионирования и подтверждений
```javascript
class ReliableUpdates {
    constructor() {
        this.lastConfirmedVersion = 0;
        this.currentVersion = 0;
        this.pendingUpdates = new Map(); // version -> update data
        this.ackTimeout = 5000; // 5 секунд на ACK
    }

    sendUpdate(updateData) {
        this.currentVersion++;
        const version = this.currentVersion;
        
        const message = {
            version,
            ...updateData,
            timestamp: new Date().toISOString()
        };

        // Сохраняем для возможной переотправки
        this.pendingUpdates.set(version, message);

        // Отправляем
        sendToServer('versioned_update', message);

        // Устанавливаем таймер для переотправки
        setTimeout(() => {
            if (this.pendingUpdates.has(version)) {
                console.warn(`No ACK for version ${version}, resending...`);
                sendToServer('versioned_update', message);
            }
        }, this.ackTimeout);
    }

    handleAck(version) {
        // Удаляем подтвержденные версии
        for (let v = this.lastConfirmedVersion + 1; v <= version; v++) {
            this.pendingUpdates.delete(v);
        }
        this.lastConfirmedVersion = version;
    }

    resendUnconfirmedUpdates() {
        for (const [version, message] of this.pendingUpdates) {
            if (version > this.lastConfirmedVersion) {
                console.log(`Resending unconfirmed version ${version}`);
                sendToServer('versioned_update', message);
            }
        }
    }
}

// Обработка ACK от сервера
function handleServerMessage(eventType, messageData) {
    if (eventType === 'update_ack') {
        reliableUpdates.handleAck(messageData.version);
    }
    // ... другие обработчики
}
```

#### Серверная часть для ACK
```php
// WebSocketServiceProvider.php
private function handleVersionedUpdate(int $sellerId, array $data, ?string $channel = null): void
{
    $version = $data['version'] ?? null;
    
    // Обрабатываем обновление
    $this->processUpdate($sellerId, $data);
    
    // Отправляем ACK
    if ($version && $channel) {
        \App\Events\ExtensionEvents::sendSmart('update_ack', $sellerId, [
            'version' => $version,
            'status' => 'confirmed'
        ], 'Обновление подтверждено');
    }
}
```

### Адаптивная частота отправки

#### Определение активности
```javascript
class ActivityMonitor {
    constructor() {
        this.lastTradeActivity = 0;
        this.lastStatusChange = 0;
        this.activeConnectionsCount = 0;
    }

    updateActivity() {
        this.lastTradeActivity = Date.now();
    }

    updateStatusChange() {
        this.lastStatusChange = Date.now();
    }

    isActive() {
        const now = Date.now();
        const recentTradeActivity = (now - this.lastTradeActivity) < (10 * 60 * 1000); // 10 минут
        const recentStatusChange = (now - this.lastStatusChange) < (5 * 60 * 1000);   // 5 минут
        const hasActiveConnections = this.activeConnectionsCount > 0;

        return recentTradeActivity || recentStatusChange || hasActiveConnections;
    }

    getOptimalInterval() {
        if (this.isActive()) {
            return 24000; // 24 секунды при активности
        } else {
            return 120000; // 2 минуты при неактивности
        }
    }
}

// Использование адаптивной частоты
const activityMonitor = new ActivityMonitor();

function startAdaptiveChecking() {
    function scheduleNext() {
        const interval = activityMonitor.getOptimalInterval();
        setTimeout(() => {
            checkStatusWebsocket();
            scheduleNext();
        }, interval);
    }
    scheduleNext();
}
```

### WebSocket управление соединениями

#### Улучшенный keep-alive с быстрым восстановлением
```javascript
class WebSocketManager {
    constructor() {
        this.wsConnection = null;
        this.keepAliveInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.pendingMessages = []; // Очередь сообщений при разрыве
    }

    connect() {
        this.wsConnection = new WebSocket(WS_URL);
        
        this.wsConnection.onopen = () => {
            this.reconnectAttempts = 0;
            this.startKeepAlive();
            this.flushPendingMessages();
        };

        this.wsConnection.onclose = (event) => {
            if (event.code !== 1000 && this.reconnectAttempts < this.maxReconnectAttempts) {
                this.scheduleReconnect();
            }
        };

        this.wsConnection.onerror = () => {
            // Ошибки обрабатываем тихо
        };
    }

    startKeepAlive() {
        this.keepAliveInterval = setInterval(() => {
            if (this.wsConnection?.readyState === WebSocket.OPEN) {
                this.send({ type: 'ping', timestamp: Date.now() });
            }
        }, 30000); // 30 секунд
    }

    send(message) {
        if (this.wsConnection?.readyState === WebSocket.OPEN) {
            this.wsConnection.send(JSON.stringify(message));
        } else {
            // Сохраняем в очередь для отправки после переподключения
            this.pendingMessages.push(message);
        }
    }

    flushPendingMessages() {
        while (this.pendingMessages.length > 0) {
            const message = this.pendingMessages.shift();
            this.send(message);
        }
    }

    scheduleReconnect() {
        this.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }
}
```

## Итоговая архитектура

### Новый алгоритм работы расширения
1. **Heartbeat (каждые 24 сек)**: отправка минимальной сессии
2. **Трейды**: только при изменениях через кеш
3. **Батчинг**: накопление изменений за 15 сек
4. **Сжатие**: для массивов >1KB
5. **Версионирование**: гарантия доставки
6. **Адаптивность**: частота зависит от активности

### Ожидаемые результаты
- **Снижение трафика на 70-80%**
- **Уменьшение нагрузки на Steam API**
- **Повышение надежности доставки**
- **Масштабируемость до 1000+ продавцов**

### План внедрения
1. Реализация минимальной сессии (быстрый эффект)
2. Кеширование трейдов (основная оптимизация)
3. Батчинг и сжатие (fine-tuning)
4. Система версий (надежность)
5. Адаптивность (долгосрочная оптимизация)