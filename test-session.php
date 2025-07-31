<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Services\SteamSessionCache;
use App\Models\Client;

$sessionCache = app(SteamSessionCache::class);

$sellerId = 3;
$buyerId = 4;

echo "=== Проверка сессий и данных ===\n\n";

// Проверяем продавца
$seller = Client::find($sellerId);
if ($seller) {
    echo "✅ Продавец найден: {$seller->name} (Steam ID: {$seller->steam_id})\n";
} else {
    echo "❌ Продавец не найден\n";
}

// Проверяем покупателя
$buyer = Client::find($buyerId);
if ($buyer) {
    echo "✅ Покупатель найден: {$buyer->name} (Steam ID: {$buyer->steam_id})\n";
    echo "   Trade URL: " . ($buyer->steam_trade_url ?: 'НЕ УКАЗАН') . "\n";
} else {
    echo "❌ Покупатель не найден\n";
}

echo "\n";

// Проверяем сессию продавца
if ($sessionCache->has($sellerId)) {
    echo "✅ Сессия продавца найдена в кеше\n";
    
    $sessionInfo = $sessionCache->getSessionInfo($sellerId);
    $sessionData = $sessionCache->get($sellerId);
    $expiresIn = $sessionCache->getExpiresInSeconds($sellerId);
    
    echo "   Обновлена: {$sessionInfo['updated_at']}\n";
    echo "   Истекает через: {$expiresIn} секунд\n";
    
    if (isset($sessionData['sessionid'])) {
        echo "   ✅ sessionid: " . substr($sessionData['sessionid'], 0, 10) . "...\n";
    } else {
        echo "   ❌ sessionid: НЕ НАЙДЕН\n";
    }
    
    if (isset($sessionData['steamLoginSecure'])) {
        echo "   ✅ steamLoginSecure: " . substr($sessionData['steamLoginSecure'], 0, 20) . "...\n";
    } else {
        echo "   ❌ steamLoginSecure: НЕ НАЙДЕН\n";
    }
    
} else {
    echo "❌ Сессия продавца НЕ найдена в кеше\n";
    echo "   Убедитесь, что расширение браузера активно и пользователь залогинен в Steam\n";
}

echo "\n";

// Проверяем все активные сессии
$allSessions = $sessionCache->getAllActiveSessions();
echo "📊 Всего активных сессий в кеше: " . count($allSessions) . "\n";
foreach ($allSessions as $session) {
    echo "   - Client ID: {$session['client_id']}, истекает: {$session['expires_at']}\n";
}