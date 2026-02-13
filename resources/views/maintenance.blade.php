<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Технические работы - CS-Skins</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .container {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }

        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #f2a93e;
        }

        p {
            font-size: 1.2rem;
            color: #a0aec0;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            background: #f2a93e;
            border-radius: 50%;
            animation: blink 1s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/images/logo_white.svg?v={{ filemtime(public_path('images/logo_white.svg')) }}" alt="CS-Skins" class="logo" onerror="this.style.display='none'">
        <div class="icon">🔧</div>
        <h1>Технические работы</h1>
        <p>
            Мы проводим плановое обслуживание сайта для улучшения качества сервиса.
            Пожалуйста, зайдите позже.
        </p>
        <div class="status">
            <span class="status-dot"></span>
            <span>Ведутся работы</span>
        </div>
    </div>
</body>
</html>
