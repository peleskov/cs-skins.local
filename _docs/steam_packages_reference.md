# Steam Packages Reference Guide

## Обзор
Документация по возможностям пакетов `steam-tradeoffer-manager` и `steamcommunity` для работы со Steam API, трейдами и предметами.

## 1. Управление куками и сессией

### Получение куков из браузерного расширения
```javascript
// Формат куков от расширения
const cookiesData = {
    sessionid: '653d26d0497c2e3cce522edb',
    steamLoginSecure: '76561198985797138%7C%7C...',
    steam_id: '76561198985797138'
};

// Формирование куков для пакетов
const cookies = [
    `sessionid=${cookiesData.sessionid}`,
    `steamLoginSecure=${cookiesData.steamLoginSecure}`
];

// Установка куков
manager.setCookies(cookies, (err) => {
    if (!err) {
        console.log('Куки установлены');
    }
});
```

## 2. Работа с трейдами

### Проверка статуса трейда
```javascript
manager.getOffer(tradeOfferId, (err, offer) => {
    if (!err) {
        console.log('ID:', offer.id);
        console.log('Статус:', offer.state, '(' + manager.constructor.ETradeOfferState[offer.state] + ')');
        console.log('Партнер:', offer.partner.getSteamID64());
        console.log('Предметов отдаем:', offer.itemsToGive.length);
        console.log('Предметов получаем:', offer.itemsToReceive.length);
    }
});
```

### Статусы трейдов (ETradeOfferState)
- 1: Invalid
- 2: Active  
- 3: Accepted
- 4: Countered
- 5: Expired
- 6: Canceled
- 7: Declined
- 8: InvalidItems
- 9: CreatedNeedsConfirmation
- 10: CanceledBySecondFactor
- 11: InEscrow

### Получение всех трейдов
```javascript
manager.getOffers(manager.constructor.EOfferFilter.All, (err, sent, received) => {
    console.log('Отправленных:', sent.length);
    console.log('Полученных:', received.length);
});
```

## 3. Работа с инвентарем

### Получение инвентаря
```javascript
// Свой инвентарь
manager.getInventoryContents(appid, contextid, tradableOnly, (err, inventory) => {
    // appid: 730 для CS:GO
    // contextid: 2 для CS:GO
    // tradableOnly: true/false
});

// Чужой инвентарь
manager.getUserInventoryContents(steamID, appid, contextid, tradableOnly, (err, inventory) => {
    // ...
});
```

### Структура предмета (EconItem)
```javascript
{
    appid: 730,
    contextid: '2',
    assetid: '38483057267',
    classid: '310777118',
    instanceid: '302028390',
    amount: 1,
    
    // Названия
    name: 'Tec-9 | Army Mesh',
    market_name: 'Tec-9 | Army Mesh (Field-Tested)',
    market_hash_name: 'Tec-9 | Army Mesh (Field-Tested)',
    type: 'Consumer Grade Pistol',
    
    // Статусы
    tradable: true,
    marketable: true,
    commodity: false,
    
    // Изображения
    icon_url: 'hash...',
    icon_url_large: undefined, // обычно не заполнено
    
    // Описания и теги
    descriptions: [...],
    tags: [...],
    
    // Действия
    actions: [{
        name: 'Inspect in Game...',
        link: 'steam://rungame/730/...'
    }],
    
    // Ограничения
    market_tradable_restriction: 7,
    market_marketable_restriction: 7
}
```

### Методы предмета
- `item.getImageURL()` - получить URL изображения
- `item.getLargeImageURL()` - получить URL большого изображения (возвращает то же что и getImageURL)
- `item.getTag(category)` - получить тег по категории

## 4. Работа с изображениями

### Размеры изображений
```javascript
const baseUrl = 'https://steamcommunity-a.akamaihd.net/economy/image/';
const iconHash = item.icon_url;

// Разные размеры
const small = `${baseUrl}${iconHash}/96fx96f`;
const medium = `${baseUrl}${iconHash}/256fx256f`;
const large = `${baseUrl}${iconHash}/512fx512f`;
const max = `${baseUrl}${iconHash}/`; // максимальный размер
```

## 5. Теги предметов

### Основные категории тегов
- **Type**: тип предмета (Pistol, Rifle, Knife, Sticker)
- **Weapon**: конкретное оружие (weapon_ak47, weapon_awp)
- **Quality**: качество (Normal, StatTrak, Souvenir)
- **Rarity**: редкость (Consumer Grade, Industrial Grade, Mil-Spec, Restricted, Classified, Covert)
- **Exterior**: состояние (Factory New, Minimal Wear, Field-Tested, Well-Worn, Battle-Scarred)
- **ItemSet**: коллекция

### Получение тегов
```javascript
const qualityTag = item.getTag('Quality');
const rarityTag = item.getTag('Rarity');
const exteriorTag = item.getTag('Exterior');
```

## 6. Работа с маркетом

### Поиск на маркете
```javascript
community.marketSearch({
    query: 'AK-47',
    appid: 730
}, (err, results) => {
    results.forEach(item => {
        console.log(item.market_hash_name);
        console.log(`Цена: $${item.price/100}`);
        console.log(`Количество: ${item.quantity}`);
    });
});
```

### Получение информации о предмете на маркете
```javascript
community.getMarketItem(appid, hashName, currency, (err, item) => {
    console.log('Lowest Price:', item.lowestPrice ? `$${item.lowestPrice/100}` : 'N/A');
    console.log('Quantity:', item.quantity);
    console.log('Commodity:', item.commodity);
    
    if (item.commodity) {
        console.log('Buy Orders:', item.buyQuantity);
        console.log('Highest Buy Order:', `$${item.highestBuyOrder/100}`);
    }
});
```

## 7. Inspect ссылки

### Формат inspect ссылки
```
steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S%owner_steamid%A%assetid%D12143607454959615792
```

### Параметры
- **S**: Steam ID владельца или %owner_steamid
- **A**: Asset ID предмета или %assetid  
- **D**: дополнительный параметр (возможно содержит данные о состоянии)

## 8. Ограничения и недостатки

### Что НЕ доступно через базовые пакеты:
1. **Float/Wear Value** - точное числовое значение износа
2. **Paint Seed** - паттерн скина
3. **3D модели** - нет прямого доступа
4. **Скриншоты предметов** - нет методов
5. **Детальная информация из inspect** - требует дополнительных API

### Обходные пути:
- Использовать сторонние сервисы (CSGOFloat, CS.MONEY API)
- Парсить HTML страниц Steam Community
- Использовать недокументированные API endpoints

## 9. Архитектура взаимодействия

### Рекомендуемая архитектура:
1. **Браузерное расширение**:
   - Извлекает куки из браузера
   - Отправляет их на сервер через WebSocket
   - Выполняет действия требующие браузер (создание трейдов)

2. **Сервер (Node.js)**:
   - Получает куки от расширения
   - Использует steam-tradeoffer-manager для:
     - Проверки статусов трейдов
     - Получения инвентарей
     - Работы с маркетом
   - Хранит и обновляет информацию в БД

3. **Laravel приложение**:
   - Управляет бизнес-логикой
   - Отправляет команды расширению через WebSocket
   - Запускает Node.js скрипты для работы со Steam API

## 10. Примеры использования

### Полный цикл проверки трейда
```javascript
// 1. Получаем куки от расширения
const cookies = await getCookiesFromExtension();

// 2. Устанавливаем куки в менеджер
manager.setCookies(cookies, (err) => {
    if (err) return handleError(err);
    
    // 3. Проверяем статус трейда
    manager.getOffer(tradeId, (err, offer) => {
        if (err) return handleError(err);
        
        // 4. Анализируем результат
        if (offer.state === TradeOfferManager.ETradeOfferState.Accepted) {
            // Трейд принят
            processAcceptedTrade(offer);
        }
    });
});
```

### Получение и анализ инвентаря
```javascript
manager.getInventoryContents(730, 2, true, (err, inventory) => {
    if (err) return handleError(err);
    
    inventory.forEach(item => {
        // Получаем информацию о предмете
        const rarity = item.getTag('Rarity');
        const exterior = item.getTag('Exterior');
        const imageUrl = item.getImageURL();
        
        // Сохраняем в БД
        saveItemToDatabase({
            assetId: item.assetid,
            name: item.market_hash_name,
            rarity: rarity?.internal_name,
            exterior: exterior?.internal_name,
            image: imageUrl,
            tradable: item.tradable
        });
    });
});
```

## 11. Дополнительные пакеты для расширенных возможностей

### Найденные пакеты для работы с CS:GO предметами:

1. **csgo** - A node-steam plugin for CS:GO
   - Основной пакет для работы с CS:GO через Steam
   
2. **csgo-cdn** - Retrieves the Steam CDN URLs for CS:GO Item Images
   - Получение CDN ссылок на изображения предметов
   - Keywords: steam, csgo, global offensive, stickers, cdn, images

3. **steam-web** - A wrapper for the Steam Web API
   - Обертка для Steam Web API
   
4. **csgo-fade-percentage-calculator** 
   - Калькулятор процента fade для скинов
   
5. **csgo-sharecode**
   - Работа с share-кодами CS:GO

6. **steam-market-search** - A NodeJS package for searching the steam marketplace
   - Расширенный поиск на Steam маркете

### Потенциальные возможности:
- Получение float values через специализированные API
- Доступ к CDN изображениям высокого качества
- Расчет специфических параметров скинов (fade percentage)
- Расширенная работа с маркетом

### TODO на завтра:
- Установить и протестировать csgo-cdn для получения качественных изображений
- Проверить пакет csgo для дополнительных возможностей
- Исследовать возможности получения float values через эти пакеты

## Заключение

Пакеты `steam-tradeoffer-manager` и `steamcommunity` предоставляют базовый функционал для работы со Steam API. Для расширенных возможностей (float values, 3D модели) требуется использование дополнительных сервисов или API. Существуют специализированные пакеты (csgo, csgo-cdn), которые могут предоставить дополнительные возможности для работы с CS:GO предметами.