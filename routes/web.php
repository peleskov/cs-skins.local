<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\InventoryController;

// Публичные маршруты
Route::controller(WebController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/item/{id}', 'item')->name('item');
    Route::get('/cart', 'cart')->name('cart');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/doc/{slug}', 'doc')->name('doc');
    Route::get('/locale/{locale}', 'setLocale')->name('locale');
});

// Маршруты маркетплейса
Route::prefix('marketplace')->name('marketplace.')->controller(MarketplaceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/api/listings', 'getListings')->name('api.listings');
    Route::get('/api/categories', 'getCategories')->name('api.categories');
    Route::get('/api/tags', 'getTags')->name('api.tags');
    Route::get('/api/stats', 'getFilterStats')->name('api.stats');
    Route::get('/api/search', 'search')->name('api.search');
    Route::get('/{listing}', 'show')->name('show');
    Route::get('/api/listing/{listing}/similar', 'getSimilarListings')->name('api.similar');
});

// API маршруты
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/marketplace/listing/{listing}', [MarketplaceController::class, 'getListingDetails'])->name('marketplace.listing');
    Route::get('/translations/items', [MarketplaceController::class, 'getTranslations'])->name('translations.items');
    
    // Маршруты для корзины (заглушки)
    Route::post('/cart/add', function () {
        return response()->json(['message' => 'Функция добавления в корзину будет реализована позже'], 501);
    })->name('cart.add');
    
    Route::post('/marketplace/quick-buy', function () {
        return response()->json(['message' => 'Функция быстрой покупки будет реализована позже'], 501);
    })->name('marketplace.quick-buy');
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

    // Маршруты инвентаря
    Route::prefix('inventory')->name('inventory.')->controller(InventoryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/sync', 'sync')->name('sync');
        Route::post('/create-listing', 'createListing')->name('create-listing');
        Route::get('/{assetId}/sell', 'sell')->name('sell');
        Route::get('/{assetId}', 'show')->name('show');
    });
});

// Telegram webhook (не требует авторизации)
Route::post('/telegram/webhook', [ProfileController::class, 'telegramWebhook'])->name('telegram.webhook');
