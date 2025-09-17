<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Публичный канал для расширения
Broadcast::channel('extension-orders', function () {
    return true;
});

// Канал для конкретного продавца (с хешем для безопасности)
Broadcast::channel('seller-{sellerId}-{hash}', function () {
    return true;
});

// Presence канал для чата с кастомным guard
Broadcast::channel('presence-chat', function () {
    // Используем client guard
    $client = auth()->guard('client')->user();

    if ($client) {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => $client->steam_avatar
        ];
    }

    return false;
}, ['guards' => ['client']]); // Указываем guard явно
