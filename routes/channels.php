<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Публичный канал для расширения
Broadcast::channel('extension-orders', function () {
    return true;
});

// Канал для конкретного продавца
Broadcast::channel('seller-{sellerId}', function () {
    return true;
});
