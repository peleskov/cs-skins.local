<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExtensionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// API для браузерного расширения (без web middleware)
Route::prefix('ext-api')->name('extension.')->middleware('extension.cors')->controller(ExtensionController::class)->group(function () {
    Route::post('/auth', 'authenticateExtension')->name('auth');
    Route::get('/ping', 'ping')->name('ping');
    Route::get('/config', 'getConfig')->name('config');
    
    // Эндпоинты требующие авторизации (через Bearer token)
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('/orders/pending', 'getPendingOrders')->name('orders.pending');
        Route::post('/orders/{order}/trade-status', 'updateTradeStatus')->name('orders.trade-status');
        Route::get('/user', 'getUserInfo')->name('user');
        Route::get('/stats', 'getStats')->name('stats');
        Route::post('/telemetry', 'sendTelemetry')->name('telemetry');
    });
});

// SSE endpoint временно отключен (вызывал перегрузку Apache)
// Route::prefix('ext-api')->middleware('extension.cors')->group(function () {
//     Route::get('/sse/orders', [SseController::class, 'orders'])->name('extension.sse.orders');
// });

// Публичный endpoint для начальной конфигурации расширения
Route::prefix('ext-api')->group(function () {
    Route::get('/init', function() {
        return response()->json([
            'success' => true,
            'data' => [
                'api_url' => config('app.url'),
                'api_version' => '1.0.0'
            ]
        ]);
    })->name('extension.init');
});