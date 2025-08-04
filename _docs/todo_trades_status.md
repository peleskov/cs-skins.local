# Система мониторинга статусов трейдов

## Архитектура решения

### Основные принципы:
- Расширение = простой ретранслятор без состояния
- Периодически отправляет ВСЕ статусы трейдов на сервер
- Сервер сравнивает с сохраненными статусами и обрабатывает изменения
- Вся бизнес-логика только на сервере

## Схема работы

### 1. Периодическая отправка (каждые 15 секунд через chrome.alarms):
```javascript
chrome.alarms.create('checkTradeStatus', {
  periodInMinutes: 0.25 // 15 секунд
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'checkTradeStatus') {
    // Получаем ВСЕ активные трейды от Steam API
    const trades = await getSteamTradeOffers();
    
    // Отправляем ВСЕ текущие статусы как есть (без обработки)
    websocket.send(JSON.stringify({
      event: 'trades-status',
      data: trades.map(trade => ({
        trade_offer_id: trade.tradeofferid.toString(),
        status: trade.trade_offer_state // Отправляем числовой статус как есть
      }))
    }));
  }
});
```

### 2. Обработка на сервере (сравнение и изменения):
```php
// В WebSocketHandler
case 'trades-status':
    foreach ($data as $tradeData) {
        $tradeOffer = TradeOffer::where('steam_trade_offer_id', $tradeData['trade_offer_id'])->first();
        
        // Обрабатываем только если трейд есть в БД И статус изменился
        if ($tradeOffer && $tradeOffer->status !== $this->mapTradeOfferState($tradeData['status'])) {
            $mappedStatus = $this->mapTradeOfferState($tradeData['status']);
            $this->processStatusChange($tradeOffer, $mappedStatus);
        }
    }
    break;
```

## Steam API методы

### GetTradeOffers (батч-запрос):
```
GET https://api.steampowered.com/IEconService/GetTradeOffers/v1/
Параметры:
- access_token: извлекается из steamLoginSecure cookie
- get_sent_offers: 1
- active_only: 1
- language: english

Ответ содержит все активные трейды пользователя с их статусами
```

### Реальная структура ответа Steam API:
```json
{
  "response": {
    "trade_offers_sent": [
      {
        "tradeofferid": "8317925356",
        "accountid_other": 1828822098,
        "message": "",
        "expiration_time": 1755338577,
        "trade_offer_state": 9,
        "items_to_give": [
          {
            "appid": 730,
            "contextid": "2",
            "assetid": "38483057267",
            "classid": "310777118",
            "instanceid": "302028390",
            "amount": "1",
            "missing": false,
            "est_usd": "1"
          }
        ],
        "is_our_offer": true,
        "time_created": 1754128977,
        "time_updated": 1754128977,
        "from_real_time_trade": false,
        "escrow_end_date": 0,
        "confirmation_method": 2,
        "eresult": 1,
        "delay_settlement": true
      }
    ],
    "next_cursor": 0
  }
}
```

### Ключевые поля трейда:
- `tradeofferid`: уникальный ID трейда (строка)
- `trade_offer_state`: числовой статус трейда
- `accountid_other`: Steam ID получателя
- `expiration_time`: Unix timestamp истечения трейда
- `time_created`, `time_updated`: временные метки создания/обновления
- `items_to_give`: массив передаваемых предметов
- `confirmation_method`: метод подтверждения (2 = мобильное подтверждение)
- `delay_settlement`: требуется ли подтверждение

### Маппинг статусов Steam (на сервере):
```php
// В TradeService или обработчике WebSocket
private function mapTradeOfferState(int $state): string
{
    return match($state) {
        1 => 'Invalid',
        2 => 'Active',
        3 => 'Accepted', 
        4 => 'Countered',
        5 => 'Expired',
        6 => 'Canceled',
        7 => 'Declined',
        8 => 'InvalidItems',
        9 => 'CreatedNeedsConfirmation',
        10 => 'CanceledBySecondFactor',
        11 => 'InEscrow',
        default => 'Unknown'
    };
}
```

## Бизнес-логика обработки статусов

### При получении статуса:
- **Active/CreatedNeedsConfirmation** → продолжаем мониторинг
- **Accepted** → завершаем заказ, уведомляем покупателя
- **Declined/Expired/Canceled** → отменяем заказ, возвращаем средства
- **InvalidItems** → отменяем с особым сообщением

### Безопасность:
- Расширение передает сырые данные от Steam API (без обработки)
- Сервер сам маппит статусы и сравнивает с БД
- Валидация что трейд принадлежит пользователю

## План реализации

### Этап 1: Тестирование Steam API ✅ ЗАВЕРШЕН
- [x] Добавить тестовую кнопку в расширение
- [x] Проверить формат ответа GetTradeOffers
- [x] Убедиться в корректности извлечения access_token
- [x] Изучить реальную структуру данных Steam API

### Этап 2: Реализация мониторинга
- [ ] Интегрировать отправку трейдов в существующий цикл getSteamSession
- [ ] Реализовать отправку трейдов через WebSocket на сервер
- [ ] Добавить обработку массива трейдов в sendToServer

### Этап 3: Серверная обработка
- [ ] Добавить обработчик 'trades-status' в WebSocketHandler
- [ ] Реализовать маппинг числовых статусов в строковые
- [ ] Реализовать processStatusChange метод для обработки изменений
- [ ] Добавить логирование изменений статусов

### Этап 4: Тестирование
- [ ] Тестирование полного цикла отправки трейдов на сервер
- [ ] Проверка обработки всех типов статусов
- [ ] Тестирование на реальных изменениях статусов трейдов

## Важные моменты

1. **Простота расширения** - только получает и передает данные без обработки
2. **Централизованная логика** - все сравнения и решения на сервере
3. **Надежность** - нет состояния в расширении, которое может потеряться
4. **Масштабируемость** - легко изменить логику без обновления расширения
5. **Производительность** - один запрос к Steam API каждые 15 секунд