<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthController;

// Публичные маршруты
Route::controller(WebController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/marketplace', 'marketplace')->name('marketplace');
    Route::get('/item/{id}', 'item')->name('item');
    Route::get('/cart', 'cart')->name('cart');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/doc/{slug}', 'doc')->name('doc');
    Route::get('/locale/{locale}', 'setLocale')->name('locale');
});

// Маршруты авторизации
Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    Route::get('/steam', 'redirectToSteam')->name('steam');
    Route::get('/steam/callback', 'handleSteamCallback')->name('steam.callback');
    Route::get('/logout', 'logout')->name('logout');
});

// Страница авторизации
Route::get('/login', function () {
    return redirect()->route('auth.steam');
})->name('login');

// Профиль пользователя (требует авторизации)
Route::middleware(['auth:client'])->group(function () {
    Route::get('/profile', function () {
        $view = app(ProfileController::class)->index();
        return response($view)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('profile');
    Route::post('/profile/update-email', [ProfileController::class, 'updateEmail'])->name('profile.update.email');
    Route::get('/email/verify/{id}/{hash}', [ProfileController::class, 'verifyEmail'])->name('profile.verify.email');
    Route::post('/email/resend', [ProfileController::class, 'resendVerification'])->name('profile.resend.verification');
    Route::post('/profile/update-trade-url', [ProfileController::class, 'updateTradeUrl'])->name('profile.update.trade-url');
    Route::match(['GET', 'POST'], '/profile/telegram/verify', [ProfileController::class, 'verifyTelegram'])->name('profile.telegram.verify');
    Route::post('/profile/telegram/unlink', [ProfileController::class, 'unlinkTelegram'])->name('profile.telegram.unlink');
});

// Telegram webhook (не требует авторизации)
Route::post('/telegram/webhook', [ProfileController::class, 'telegramWebhook'])->name('telegram.webhook');
