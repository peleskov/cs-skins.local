# Audit: Error Handling в Frontend

## Найденные места с локальной обработкой ошибок (43 места):

### FavoriteButton.vue
- Line 82: `window.toast.error(data.message || 'Не удалось обновить избранное');`
- Line 86: `window.toast.error(handleApiError(error));`

### CartButton.vue  
- Line 113: `window.toast.error(data.message || 'Не удалось добавить товар в корзину');`
- Line 117: `window.toast.error(handleApiError(error));`
- Line 141: `window.toast.error(data.message || 'Не удалось удалить товар из корзины');`
- Line 145: `window.toast.error(handleApiError(error));`

### profile/Favorites.vue
- Line 174: `window.toast.error(data.message || 'Ошибка загрузки избранного');`
- Line 178: `window.toast.error(handleApiError(error));`

### profile/Trading.vue (самый проблемный - 16 мест!)
- Line 583: `window.toast.error(data.message || 'Не удалось загрузить листинги');`
- Line 587: `window.toast.error(handleApiError(error));`
- Line 676: `window.toast.error('Сначала установите цену для листинга');`
- Line 711: `window.toast.error(data.message || 'Необходимо настроить Trade URL');`
- Line 725: `window.toast.error(data.message || 'Не удалось активировать листинг');`
- Line 730: `window.toast.error(handleApiError(error));`
- Line 761: `window.toast.error(data.message || 'Не удалось удалить предмет');`
- Line 765: `window.toast.error(handleApiError(error));`
- Line 787: `window.toast.error('Введите корректную цену');`
- Line 814: `window.toast.error(data.message || 'Не удалось обновить цену');`
- Line 818: `window.toast.error(handleApiError(error));`
- Line 855: `window.toast.error(data.message || 'Не удалось деактивировать листинг');`
- Line 859: `window.toast.error(handleApiError(error));`
- Line 890: `window.toast.error('Введите Trade URL');`
- Line 921: `window.toast.error(data.message || 'Ошибка при сохранении Trade URL');`
- Line 925: `window.toast.error(handleApiError(error));`
- Line 945: `window.toast.error(data.message || 'Не удалось возобновить листинг');`
- Line 949: `window.toast.error(handleApiError(error));`
- Line 988: `window.toast.error(data.message || 'Не удалось создать аукцион');`
- Line 995: `window.toast.error(error.response.data.error);`  ← НОВЫЙ ФОРМАТ!
- Line 997: `window.toast.error('Ошибка при создании аукциона');`

### profile/Sales.vue
- Line 242: `window.toast.error('Ошибка при загрузке заказов');`
- Line 344: `window.toast.error(response.message || 'Ошибка при отмене заказа');`
- Line 348: `window.toast.error(error.response?.data?.message || 'Ошибка при отмене заказа');`

### profile/Info.vue (много мест - 10)
- Line 411: `window.toast.error(data.message || 'Ошибка при обновлении Email');`
- Line 415: `window.toast.error('Произошла ошибка при обновлении Email');`
- Line 434: `window.toast.error(data.message || 'Не удалось отправить письмо');`
- Line 438: `window.toast.error('Произошла ошибка при отправке письма');`
- Line 490: `window.toast.error(data.message || 'Ошибка при обновлении Trade URL');`
- Line 494: `window.toast.error('Произошла ошибка при обновлении Trade URL');`
- Line 504: `window.toast.error('Trade URL не найден');`
- Line 533: `window.toast.error(data.message || 'Ошибка при генерации токена');`
- Line 537: `window.toast.error('Произошла ошибка при генерации токена');`
- Line 564: `window.toast.error(data.message || 'Ошибка при регенерации токена');`
- Line 568: `window.toast.error('Произошла ошибка при регенерации токена');`
- Line 576: `window.toast.error('Токен не найден');`
- Line 673: `window.toast.error(data.message || 'Ошибка при верификации');`
- Line 677: `window.toast.error('Произошла ошибка при верификации');`

### profile/Inventory.vue
- Line 342: `window.toast.error(data.message || 'Не удалось загрузить инвентарь');`
- Line 350: `window.toast.error(handleApiError(error));`
- Line 376: `window.toast.error(data.message);`
- Line 385: `window.toast.error(handleApiError(error));`
- Line 612: `window.toast.error(data.message || 'Не удалось создать листинг');`
- Line 616: `window.toast.error(handleApiError(error));`

### profile/Orders.vue
- Line 242: `window.toast.error('Ошибка при загрузке заказов');`
- Line 344: `window.toast.error(response.message || 'Ошибка при отмене заказа');`
- Line 348: `window.toast.error(error.response?.data?.message || 'Ошибка при отмене заказа');`

### Checkout.vue
- Line 188: `window.toast.error('Ошибка при загрузке корзины');`

### Cart.vue
- Line 189: `window.toast.error(data.message || 'Не удалось загрузить корзину');`
- Line 193: `window.toast.error(handleApiError(error));`
- Line 225: `window.toast.error(data.message || 'Не удалось удалить товар');`
- Line 229: `window.toast.error(handleApiError(error));`
- Line 258: `window.toast.error(data.message || 'Не удалось очистить корзину');`
- Line 262: `window.toast.error(handleApiError(error))`

### SkinDetails.vue
- Line 651: `window.toast.error(response.message);`

## Обнаруженные форматы ошибок:

1. `data.message` - стандартный Laravel формат
2. `data.error` - новый формат (используется в AuctionController)  
3. `error.response.data.message` - прямое обращение к ответу
4. `error.response.data.error` - прямое обращение к error поле
5. `handleApiError(error)` - через helper функцию
6. Хардкод строки - статичные сообщения

## Статус проверки бэкенда:
- [ ] AuctionController - возвращает `error`
- [ ] TradeController - проверить формат
- [ ] CartController - проверить формат  
- [ ] OrderController - проверить формат
- [x] **FavoritesController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Товар добавлен в избранное'}`
  - Ошибка: Laravel validation (стандартный формат)
- [x] **CartController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Товар добавлен в корзину'}`
  - Ошибка: `{'success': false, 'message': 'Ошибка...'}`
- [x] **TradeController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Листинг активирован'}`
  - Ошибка: `{'success': false, 'message': 'Произошла ошибка...'}`
  - Примечание: `'error'` используется только в Log::error(), не в ответах
- [x] **OrderController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Товар успешно куплен!'}`  
  - Ошибка: `{'success': false, 'message': 'Ошибка при покупке...'}`
- [x] **AuctionController** - ✅ использует `message` (ИСПРАВЛЕНО!)
  - Успех: `{'success': true, 'auction': {...}}`
  - Ошибка: `{'success': false, 'message': 'У вас нет доступа к аукционам...'}`
- [x] **ProfileController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Trade URL успешно обновлен!'}`
  - Ошибка: `{'success': false, 'message': 'Email адрес не указан.'}`
  - Примечание: `'error'` используется только в Log::warning(), не в ответах
- [x] **InventoryController** - ✅ использует `message` (стандартный Laravel формат)
  - Успех: `{'success': true, 'message': 'Инвентарь обновлен'}`
  - Ошибка: `{'success': false, 'message': 'Произошла ошибка...'}`
  - Примечание: `'error'` используется только в Log::error(), не в ответах

## План действий:
1. Проверить, какой формат ошибок возвращает каждый контроллер
2. Стандартизировать формат на бэкенде
3. Обновить глобальный обработчик под стандартный формат
4. Удалить все локальные обработчики ошибок