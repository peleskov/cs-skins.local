<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новое сообщение с сайта</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #ff8d2f;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .field {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #ff8d2f;
            border-radius: 4px;
        }
        .field-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .field-value {
            color: #333;
            word-wrap: break-word;
        }
        .message-field {
            background-color: #f0f8ff;
            border-left: 4px solid #2196F3;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Новое сообщение с сайта</h1>
        </div>

        <div class="field">
            <div class="field-label">Имя:</div>
            <div class="field-value">{{ $firstName }}</div>
        </div>

        <div class="field">
            <div class="field-label">Фамилия:</div>
            <div class="field-value">{{ $lastName }}</div>
        </div>

        <div class="field">
            <div class="field-label">Email:</div>
            <div class="field-value">
                <a href="mailto:{{ $userEmail }}">{{ $userEmail }}</a>
            </div>
        </div>

        @if($phone)
        <div class="field">
            <div class="field-label">Телефон:</div>
            <div class="field-value">
                <a href="tel:{{ $phone }}">{{ $phone }}</a>
            </div>
        </div>
        @endif

        <div class="field message-field">
            <div class="field-label">Сообщение:</div>
            <div class="field-value">{{ $userMessage }}</div>
        </div>

        <div class="footer">
            <p>Это письмо было отправлено через контактную форму на сайте</p>
            <p>Дата отправки: {{ now()->format('d.m.Y H:i') }}</p>
        </div>
    </div>
</body>
</html>