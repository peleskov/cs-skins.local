<?php

use App\Services\SteamSessionCache;
use App\Models\Client;

Artisan::command('test:session {sellerId=3} {buyerId=4}', function () {
    $sellerId = (int) $this->argument('sellerId');
    $buyerId = (int) $this->argument('buyerId');
    
    $sessionCache = app(SteamSessionCache::class);

    $this->info("=== Проверка сессий и данных ===");
    $this->info("");

    // Проверяем продавца
    $seller = Client::find($sellerId);
    if ($seller) {
        $this->info("✅ Продавец найден: {$seller->name} (Steam ID: {$seller->steam_id})");
    } else {
        $this->error("❌ Продавец не найден");
    }

    // Проверяем покупателя
    $buyer = Client::find($buyerId);
    if ($buyer) {
        $this->info("✅ Покупатель найден: {$buyer->name} (Steam ID: {$buyer->steam_id})");
        $this->info("   Trade URL: " . ($buyer->steam_trade_url ?: 'НЕ УКАЗАН'));
    } else {
        $this->error("❌ Покупатель не найден");
    }

    $this->info("");

    // Проверяем сессию продавца
    if ($sessionCache->has($sellerId)) {
        $this->info("✅ Сессия продавца найдена в кеше");
        
        $sessionInfo = $sessionCache->getSessionInfo($sellerId);
        $sessionData = $sessionCache->get($sellerId);
        $expiresIn = $sessionCache->getExpiresInSeconds($sellerId);
        
        $this->info("   Обновлена: {$sessionInfo['updated_at']}");
        $this->info("   Истекает через: {$expiresIn} секунд");
        
        if (isset($sessionData['sessionid'])) {
            $this->info("   ✅ sessionid: " . substr($sessionData['sessionid'], 0, 10) . "...");
        } else {
            $this->error("   ❌ sessionid: НЕ НАЙДЕН");
        }
        
        if (isset($sessionData['steamLoginSecure'])) {
            $this->info("   ✅ steamLoginSecure: " . substr($sessionData['steamLoginSecure'], 0, 20) . "...");
        } else {
            $this->error("   ❌ steamLoginSecure: НЕ НАЙДЕН");
        }
        
    } else {
        $this->error("❌ Сессия продавца НЕ найдена в кеше");
        $this->info("   Убедитесь, что расширение браузера активно и пользователь залогинен в Steam");
    }

    $this->info("");

    // Проверяем все активные сессии
    $allSessions = $sessionCache->getAllActiveSessions();
    $this->info("📊 Всего активных сессий в кеше: " . count($allSessions));
    foreach ($allSessions as $session) {
        $this->info("   - Client ID: {$session['client_id']}, истекает: {$session['expires_at']}");
    }
});