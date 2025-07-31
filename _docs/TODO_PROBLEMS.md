# TODO ПРОБЛЕМЫ

## 1. ПРОБЛЕМА: ProcessTradeOffer has been attempted too many times (КРИТИЧНО)

**Дата:** 28.07.2025  
**Статус:** ОБНАРУЖЕНО - ТРЕБУЕТ ИСПРАВЛЕНИЯ  

### Описание проблемы
TradeOffer #4 исчерпал лимит попыток (3 tries) и был помечен как failed из-за бесконечного цикла в системе очередей.

### Причины ошибки

#### 1. **Бесконечный цикл release(15)**
```php
// ProcessTradeOffer Job
public function handle(): void
{
    if (!$this->tradeOffer->isReady()) {
        $this->release(15); // Откладываем на 15 секунд
        return;
    }
    // ... обработка
}
```

#### 2. **TradeOffer никогда не становился ready**
- TradeOffer #4 имел `is_ready = false`
- Впереди него в очереди был другой TradeOffer, который блокировал выполнение
- Каждые 15 секунд Job пытался выполниться, но всегда получал `isReady() = false`
- Job откладывался снова и снова

#### 3. **Лимит попыток исчерпан**
```php
// config/horizon.php
'trade-offers' => [
    'tries' => 3, // ПРОБЛЕМА: слишком мало для системы с release
    'timeout' => 300,
]
```

### Логические проблемы системы

#### **Проблема 1: Блокировка очереди**
```php
// Проблемный сценарий:
TradeOffer #1 - status: pending, is_ready: true  (блокирует очередь)
TradeOffer #4 - status: pending, is_ready: false (ждет освобождения)
```
Если TradeOffer #1 завис или не может быть обработан, то TradeOffer #4 никогда не станет ready.

#### **Проблема 2: Неправильная логика release**
```php
// Каждый раз откладываем на 15 секунд
$this->release(15); 
```
Это создает бесконечный цикл попыток без разрешения проблемы.

### Решения (TODO)

#### **1. Добавить таймаут для "готовности"**
```php
// Если TradeOffer не готов слишком долго - отменить
if (!$this->tradeOffer->isReady() && $this->tradeOffer->created_at->diffInMinutes() > 30) {
    $this->tradeOffer->cancel();
    Log::warning('TradeOffer отменен из-за превышения времени ожидания', [
        'trade_offer_id' => $this->tradeOffer->id
    ]);
    return;
}
```

#### **2. Увеличить лимит попыток для trade-offers queue**
```php
// config/horizon.php
'trade-offers' => [
    'tries' => 10, // Увеличить с 3 до 10
    'timeout' => 300,
    'retry_after' => 30, // Добавить retry_after
]
```

#### **3. Добавить логику разблокировки очереди**
- Создать команду для поиска заблокированных TradeOffer
- Автоматически отменять "зависшие" TradeOffer
- Активировать следующий в очереди

#### **4. Мониторинг очереди**
```php
// Artisan команда
php artisan tradeoffer:check-stuck
```

#### **5. Улучшить логику failed jobs**
```php
// ProcessTradeOffer Job
public function failed(Throwable $exception): void
{
    // При провале - освободить очередь
    if ($exception instanceof MaxAttemptsExceededException) {
        $this->tradeOffer->cancel();
        
        // Активировать следующий TradeOffer для этого продавца
        TradeOffer::where('seller_id', $this->tradeOffer->seller_id)
            ->where('status', TradeOffer::STATUS_PENDING)
            ->where('is_ready', false)
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->update(['is_ready' => true]);
    }
}
```

### Файлы для изменения
- `/app/Jobs/ProcessTradeOffer.php` - добавить таймаут и failed handler
- `/config/horizon.php` - увеличить tries до 10
- `/app/Console/Commands/CheckStuckTradeOffers.php` - новая команда мониторинга
- `/app/Models/TradeOffer.php` - добавить методы разблокировки

### Приоритет: ВЫСОКИЙ
Эта проблема может блокировать всю систему торговли.

---

## 2. TODO: Другие проблемы (добавить по мере обнаружения)

...