<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Настраиваем доверенные прокси для Cloudflare
        $middleware->trustProxies(at: '*');
        
        $middleware->alias([
            'auth.client' => \App\Http\Middleware\AuthenticateClient::class,
            'is.bot' => \App\Http\Middleware\IsBot::class,
            'extension.cors' => \App\Http\Middleware\ExtensionCors::class,
        ]);
        
        // Отключаем CSRF для API расширения
        $middleware->validateCsrfTokens(except: [
            'api/extension/*',
            'api/ext-api/*'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
