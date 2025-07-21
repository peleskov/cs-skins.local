<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\ExtensionController;

// Публичные маршруты
Route::controller(WebController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/item/{id}', 'item')->name('item');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/doc/{slug}', 'doc')->name('doc');
    Route::get('/locale/{locale}', 'setLocale')->name('locale');
});

// Страница корзины
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/checkout', [OrderController::class, 'index'])->name('checkout');

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
    // Тестовый маршрут для проверки
    Route::get('/test', function() {
        return response()->json(['status' => 'ok', 'path' => request()->path()]);
    });
    Route::get('/marketplace/listing/{listing}', [MarketplaceController::class, 'getListingDetails'])->name('marketplace.listing');
    Route::get('/translations/items', [MarketplaceController::class, 'getTranslations'])->name('translations.items');
    
    // CSRF токен
    Route::get('/csrf-token', function () {
        return response()->json(['csrf_token' => csrf_token()]);
    })->name('csrf-token');
    
    // Маршруты для корзины с rate limiting
    Route::prefix('cart')->name('cart.')->controller(CartController::class)->group(function () {
        Route::get('/', 'getItems')->name('items')->middleware('throttle:60,1');
        Route::post('/add', 'add')->name('add')->middleware('throttle:30,1'); // 30 добавлений в минуту
        Route::delete('/{listingId}', 'destroy')->name('remove')->middleware('throttle:30,1');
        Route::delete('/', 'clear')->name('clear')->middleware('throttle:10,1');
        Route::get('/check/{listingId}', 'check')->name('check')->middleware('throttle:60,1');
        Route::get('/count', 'count')->name('count')->middleware('throttle:60,1');
    });
    
    // Маршруты для заказов
    Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function () {
        Route::post('/create', 'createOrder')->name('create')->middleware(['auth:client', 'throttle:10,1']);
        Route::post('/{order}/pay', 'payOrder')->name('pay')->middleware(['auth:client', 'throttle:5,1']);
        Route::get('/my', 'getMyOrders')->name('my')->middleware(['auth:client', 'throttle:60,1']);
    });
    
    Route::post('/marketplace/quick-buy', function () {
        return response()->json(['message' => 'Функция быстрой покупки будет реализована позже'], 501);
    })->name('marketplace.quick-buy');
    
    
    // API маршруты для торговли (требуют авторизации)
    Route::middleware(['auth:client'])->group(function () {
        Route::get('/listings/my', [TradeController::class, 'getMyListings'])->name('listings.my');
        Route::post('/listings/update-price', [TradeController::class, 'updateListingPrice'])->name('listings.update-price');
        Route::post('/listings/activate', [TradeController::class, 'activateListing'])->name('listings.activate');
        Route::post('/listings/deactivate', [TradeController::class, 'deactivateListing'])->name('listings.deactivate');
        Route::post('/listings/delete', [TradeController::class, 'deleteListing'])->name('listings.delete');
        Route::post('/listings/min-price', [TradeController::class, 'getMinMarketPrice'])->name('listings.min-price');
    });
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

// Избранное (требует авторизации)
Route::middleware(['auth:client', 'throttle:60,1'])->group(function () {
    Route::get('/favorites', [FavoritesController::class, 'index'])->name('favorites');
    Route::get('/api/favorites', [FavoritesController::class, 'getFavorites'])->name('favorites.list');
    Route::post('/api/favorites/toggle', [FavoritesController::class, 'toggle'])->name('favorites.toggle')->middleware('throttle:30,1');
    Route::get('/api/favorites/check/{listing}', [FavoritesController::class, 'check'])->name('favorites.check');
});

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
    Route::post('/profile/extension-token/generate', [ProfileController::class, 'generateExtensionToken'])->name('profile.extension-token.generate');
    Route::post('/profile/extension-token/regenerate', [ProfileController::class, 'regenerateExtensionToken'])->name('profile.extension-token.regenerate');

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
