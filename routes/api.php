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
    
    // Эндпоинты требующие авторизации (через Bearer token)
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('/user', 'getUserInfo')->name('user');
        Route::post('/log-error', 'logError')->name('log-error');
        Route::post('/trade-status', 'updateTradeStatus')->name('trade-status');
    });
});
