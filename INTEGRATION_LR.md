# Инструкция по интеграции с CS-Skins

## 1. Регистрация партнёра

**URL:** `POST https://cs-skins.s1temaker.ru/api/partners`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "email": "partner@example.com",
    "secret": "060d5cfd10606e7149df3e4a024d3f495b608e214af9f81c9ea32303f24ba09c"
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

---

## 2. События, которые мы отправляем в LR

| Событие          | Когда срабатывает                                                   |
|------------------|---------------------------------------------------------------------|
| `registration`   | Пользователь привязался к партнёру (регистрация или смена партнёра) |
| `deposit`        | Пополнение баланса                                                  |
| `subscription`   | Первая покупка подписки                                             |
| `rebill`         | Успешное продление подписки (автосписание)                          |
| `unsubscription` | Подписка отменена или истекла                                       |

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
https://cs-skins.s1temaker.ru/l/premium?utm_medium=cpa&utm_source=partners&utm_content={partner_id}&utm_campaign={link_id}
```

**Пример на произвольную страницу:**
```
https://cs-skins.s1temaker.ru/cases?utm_medium=cpa&utm_source=partners&utm_content=1&utm_campaign=100
```
