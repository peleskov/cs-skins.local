# Инструкция по интеграции с CS-Skins

Прод-домен: `https://cs-skins.pro`

## 1. Регистрация партнёра (LR → cs-skins)

**URL:** `POST https://cs-skins.pro/api/partners`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "email": "partner@example.com",
    "secret": "060d5cfd10606e7149df3e4a024d3f495b608e214af9f81c9ea32303f24ba09c060d5cfd10606e7149df3e4a024d3f495b608e214af9f81c9ea32303f24ba09c"
}
```

**Успешный ответ (200):**
```json
{
    "partner_id": 1
}
```

`partner_id` — уникальный ID партнёра, используется для построения реферальных ссылок.
Повторный вызов с тем же email вернёт тот же `partner_id`.

**Ошибки:**
- `403 {"error":"Forbidden"}` — неверный `secret`
- `422` — ошибка валидации (`email`, `secret` обязательны)

---

## 2. События, которые cs-skins отправляет в LR

Запросы идут от cs-skins на `https://losreferidos.club/adv_api/{adv_id}/?...` (GET).

| Событие          | Когда срабатывает                                                   |
|------------------|---------------------------------------------------------------------|
| `registration`   | Пользователь привязался к партнёру (регистрация или смена партнёра) |
| `deposit`        | Пополнение баланса                                                  |
| `subscription`   | Первая покупка подписки                                             |
| `rebill`         | Успешное продление подписки (автосписание)                          |
| `unsubscription` | Подписка отменена или истекла                                       |

**Параметры запроса:**
- `hash` — API-hash рекламодателя
- `goal_name` — название события из таблицы выше
- `client_id` — `referral.id` на стороне cs-skins (используется как external_id на стороне LR)
- `partner_id` — ID партнёра
- `link_id` — ID ссылки (если был передан в UTM)
- `order_id` — ID платежа (0 для `registration` / `unsubscription`)
- `promo_code`, `amount` — если в платеже был применён промокод
- `client_name` — опционально, если у клиента указано имя

---

## 3. Реферальные ссылки

Формат: любая страница сайта с UTM-метками.

**Параметры:**
- `utm_medium=cpa` — обязательно
- `utm_source=partners` — обязательно
- `utm_content={partner_id}` — ID партнёра из API
- `utm_campaign={link_id}` — ID ссылки (опционально)

**Лендинги:**
```
https://cs-skins.pro/l/premium?utm_medium=cpa&utm_source=partners&utm_content={partner_id}&utm_campaign={link_id}
```

**Пример на произвольную страницу:**
```
https://cs-skins.pro/cases?utm_medium=cpa&utm_source=partners&utm_content=1&utm_campaign=100
```

---

## 4. Webhook создания промокодов (LR → cs-skins)

**URL для кабинета LR (опция «Отправлять новые промокоды на URL»):**
```
https://cs-skins.pro/api/lr/promo-codes
```

**Headers:**
```
Content-Type: application/json
X-Adv-Hash: <API-hash рекламодателя>
```

**Тело запроса:**
```json
{
    "event": "promo_code_created",
    "advertiser_id": 36,
    "promo_code": {
        "code": "TEST50",
        "offer_id": 220,
        "partner_id": 1,
        "type": "percent",
        "value": 50,
        "min_deposit": 0,
        "valid_from": "2026-01-01",
        "valid_to": "2026-12-31",
        "total_usage_limit": 100,
        "per_user_usage_limit": 1,
        "is_active": true
    }
}
```

**Обязательные поля:** `event`, `advertiser_id`, `promo_code.code`, `promo_code.partner_id`, `promo_code.type` (`fixed`|`percent`), `promo_code.value`, `promo_code.is_active`.
**Опциональные:** `promo_code.offer_id`, `min_deposit`, `valid_from`, `valid_to`, `total_usage_limit`, `per_user_usage_limit`.

**Ответы:**
- `200 {"status":"ok","promocode_id":N}` — промокод создан
- `403 {"message":"Forbidden"}` — неверный `X-Adv-Hash`
- `422 {"message":"...","errors":{...}}` — ошибка валидации (дубликат `code`, неизвестный `partner_id`, неверный `advertiser_id`, нарушение схемы)

Таймаут обработки — до 10 секунд.
