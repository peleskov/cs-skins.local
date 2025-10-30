<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
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
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ChatController;
use App\Models\Currency;

// Публичные маршруты
Route::controller(WebController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactSend')->name('contact.send');
    Route::get('/doc/{slug}', 'doc')->name('doc');
    Route::get('/locale/{locale}', 'setLocale')->name('locale');
});


// Страница корзины
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/checkout', [OrderController::class, 'index'])->name('checkout');

// Маршруты маркетплейса (фронтенд)
Route::prefix('marketplace')->name('marketplace.')->controller(MarketplaceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{listing}', 'show')->name('show');
});

// Маршруты аукционов (фронтенд)
Route::prefix('auctions')->name('auctions.')->controller(AuctionController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{listing}', 'show')->name('show');
});

// API маршруты
Route::prefix('api')->name('api.')->group(function () {

    // Маркетплейс API
    Route::prefix('marketplace')->name('marketplace.')->controller(MarketplaceController::class)->group(function () {
        Route::get('/listings', 'getListings')->name('listings');
        Route::get('/categories', 'getCategories')->name('categories');
        Route::get('/tags', 'getTags')->name('tags');
        Route::get('/stats', 'getFilterStats')->name('stats');
        Route::get('/search', 'search')->name('search');
        Route::get('/listing/{listing}', 'getListingDetails')->name('listing');
        Route::get('/listing/{listing}/similar', 'getSimilarListings')->name('listing.similar');
    });

    Route::get('/translations/items', [MarketplaceController::class, 'getTranslations'])->name('translations.items');

    // Публичные аукционы API
    Route::prefix('auctions')->name('auctions.')->controller(\App\Http\Controllers\AuctionController::class)->group(function () {
        Route::get('/', 'getAuctions')->name('index');
        Route::get('/{auction}/bids', 'bidHistory')->name('bids')->middleware('throttle:60,1');
    });

    // Кейсы API
    Route::prefix('cases')->name('cases.')->controller(CaseController::class)->group(function () {
        Route::get('/', 'list')->name('list')->middleware('throttle:60,1');
        Route::get('/{slug}', 'detail')->name('detail')->middleware('throttle:60,1');
        Route::post('/purchase', 'purchaseCase')->name('purchase')->middleware(['auth:client', 'throttle:10,1']);
    });


    // CSRF токен
    Route::get('/csrf-token', function () {
        return response()->json(['csrf_token' => csrf_token()]);
    })->name('csrf-token');

    // Валюты
    Route::get('/currencies', function () {
        $currencies = Currency::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name', 'symbol', 'is_primary', 'exchange_rate']);

        return response()->json($currencies);
    })->name('currencies');

    // Маршруты для корзины с rate limiting
    Route::prefix('cart')->name('cart.')->controller(CartController::class)->group(function () {
        Route::get('/', 'getItems')->name('items')->middleware('throttle:60,1');
        Route::post('/add', 'add')->name('add')->middleware('throttle:30,1'); // 30 добавлений в минуту
        Route::delete('/{listingId}', 'destroy')->name('remove')->middleware('throttle:30,1');
        Route::delete('/', 'clear')->name('clear')->middleware('throttle:30,1');
        Route::get('/check/{listingId}', 'check')->name('check')->middleware('throttle:60,1');
        Route::get('/count', 'count')->name('count')->middleware('throttle:60,1');
    });

    // Маршруты для заказов
    Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function () {
        Route::post('/create', 'cartBuy')->name('create')->middleware(['auth:client', 'throttle:10,1']);
        Route::post('/quick-buy', 'quickBuy')->name('quick-buy')->middleware(['auth:client', 'throttle:10,1']);
        Route::post('/quick-sell', 'quickSell')->name('quick-sell')->middleware(['auth:client', 'throttle:10,1']);
        Route::get('/purchases', 'getMyOrders')->name('purchases')->middleware(['auth:client', 'throttle:60,1']);
        Route::get('/sales', 'getMySales')->name('sales')->middleware(['auth:client', 'throttle:60,1']);
        Route::post('/{order}/cancel', 'cancel')->name('cancel')->middleware(['auth:client', 'throttle:10,1']);
    });



    // API маршруты требующие авторизации
    Route::middleware(['auth:client'])->group(function () {
        // Торговля
        Route::get('/listings/my', [TradeController::class, 'getMyListings'])->name('listings.my');
        Route::post('/listings/update-price', [TradeController::class, 'updateListingPrice'])->name('listings.update-price');
        Route::post('/listings/activate', [TradeController::class, 'activateListing'])->name('listings.activate');
        Route::post('/listings/deactivate', [TradeController::class, 'deactivateListing'])->name('listings.deactivate');
        Route::post('/listings/reactivate', [TradeController::class, 'reactivateListing'])->name('listings.reactivate');
        Route::post('/listings/delete', [TradeController::class, 'deleteListing'])->name('listings.delete');
        Route::post('/listings/min-price', [TradeController::class, 'getMinMarketPrice'])->name('listings.min-price');

        // Аукционы
        Route::prefix('auctions')->name('auctions.')->controller(AuctionController::class)->group(function () {
            Route::post('/', 'create')->name('create')->middleware('throttle:10,1');
            Route::get('/{auction}', 'show')->name('show');
            Route::patch('/{auction}', 'update')->name('update')->middleware('throttle:10,1');
            Route::delete('/{auction}', 'destroy')->name('destroy')->middleware('throttle:10,1');
            Route::patch('/{auction}/activate', 'activate')->name('activate')->middleware('throttle:10,1');
            Route::patch('/{auction}/deactivate', 'deactivate')->name('deactivate')->middleware('throttle:10,1');
            Route::post('/{auction}/bid', 'bid')->name('bid')->middleware('throttle:30,1');
            Route::patch('/{auction}/status', 'updateStatus')->name('update-status')->middleware('throttle:10,1');
        });

        // Профиль API
        Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
            Route::get('/me', 'getCurrentUser')->name('me')->middleware('throttle:60,1');
            Route::get('/transactions', 'getTransactions')->name('transactions')->middleware('throttle:60,1');
            Route::get('/sales-stats', 'getSalesStats')->name('sales-stats')->middleware('throttle:60,1');
        });

        // Чат API
        Route::prefix('chat')->name('chat.')->controller(ChatController::class)->group(function () {
            Route::get('/messages', 'getMessages')->name('messages')->middleware('throttle:60,1');
            Route::post('/send', 'sendMessage')->name('send')->middleware('throttle:30,1');
            Route::get('/ban-status', 'checkBanStatus')->name('ban-status')->middleware('throttle:60,1');
        });

        // Платежи API
        Route::prefix('deposit')->name('deposit.')->controller(\App\Http\Controllers\DepositController::class)->group(function () {
            Route::post('/payment-form', 'createPaymentForm')->name('payment-form')->middleware('throttle:10,1'); // Payment form
            Route::get('/status/{paymentId}', 'getPaymentStatus')->name('status')->middleware('throttle:60,1');
            Route::post('/check-status/{paymentId}', 'checkPaymentStatus')->name('check-status')->middleware('throttle:30,1');
            Route::get('/history', 'getPaymentsHistory')->name('history')->middleware('throttle:60,1');
            Route::post('/cancel/{paymentId}', 'cancelPayment')->name('cancel')->middleware('throttle:10,1');
        });

    });
});

// Требуется авторизации
Route::middleware(['auth:client'])->group(function () {
    
    // Маршруты кейсов (только для авторизованных)
    Route::prefix('cases')->name('cases.')->controller(CaseController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{slug}', 'show')->name('show');
    });

    // Пополнение баланса
    Route::get('/deposit', [\App\Http\Controllers\DepositController::class, 'index'])->name('deposit');


    Route::get('/profile', function () {
        $view = app(ProfileController::class)->index();
        return response($view)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('profile');
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::post('/update-email', [ProfileController::class, 'updateEmail'])->name('update.email');
        Route::post('/update-trade-url', [ProfileController::class, 'updateTradeUrl'])->name('update.trade-url');
        Route::post('/telegram/generate-code', [ProfileController::class, 'generateTelegramVerificationCode'])->name('telegram.generate-code');
        Route::post('/extension-token/generate', [ProfileController::class, 'generateExtensionToken'])->name('extension-token.generate');
        Route::post('/extension-token/regenerate', [ProfileController::class, 'regenerateExtensionToken'])->name('extension-token.regenerate');
        Route::post('/notification-settings', [ProfileController::class, 'updateNotificationSettings'])->name('notification-settings');
        Route::get('/sales', [ProfileController::class, 'sales'])->name('sales');

        // Аукционы
        Route::get('/auctions', [AuctionController::class, 'my'])->name('auctions');
        Route::get('/bids', [AuctionController::class, 'myBids'])->name('bids');
        Route::get('/won-auctions', [AuctionController::class, 'wonAuctions'])->name('won-auctions');
    });
    Route::get('/email/verify/{id}/{hash}', [ProfileController::class, 'verifyEmail'])->name('profile.verify.email');
    Route::post('/email/resend', [ProfileController::class, 'resendVerification'])->name('profile.resend.verification');

    // Маршруты инвентаря
    Route::prefix('inventory')->name('inventory.')->controller(InventoryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/sync', 'sync')->name('sync');
        Route::post('/create-listing', 'createListing')->name('create-listing');
        Route::get('/extension-status', 'checkExtensionStatus')->name('extension-status');
        Route::get('/{assetId}/sell', 'sell')->name('sell');
        Route::get('/{assetId}', 'show')->name('show');
    });

    // Избранное
    Route::get('/favorites', [FavoritesController::class, 'index'])->name('favorites');
    Route::get('/api/favorites', [FavoritesController::class, 'getFavorites'])->name('favorites.list')->middleware('throttle:60,1');
    Route::post('/api/favorites/toggle', [FavoritesController::class, 'toggle'])->name('favorites.toggle')->middleware('throttle:30,1');
    Route::get('/api/favorites/check/{listing}', [FavoritesController::class, 'check'])->name('favorites.check')->middleware('throttle:60,1');
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

// Telegram bot webhook (не требует авторизации)
Route::post('/api/telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

// Chat API routes
Route::middleware(['auth:client'])->prefix('api/chat')->name('api.chat.')->controller(\App\Http\Controllers\ChatController::class)->group(function () {
    Route::get('/messages', 'getMessages')->name('messages');
    Route::post('/send', 'sendMessage')->name('send')->middleware('throttle:30,1');
    Route::get('/ban-status', 'checkBanStatus')->name('ban-status');
});

// Тестовый маршрут для проверки JS
Route::get('/test-js', function() { return view('test-js'); });

// Динамические страницы (должно быть в самом конце)
Route::get('/{page}', function ($slug) {
    $page = \App\Models\Page::where('slug', $slug)->active()->firstOrFail();
    return view('page', compact('page'));
})->name('page');
