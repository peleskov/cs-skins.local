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
        // X-Forwarded-Host не принимаем — защита от Host Header Injection
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                     \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                     \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
        );

        // Добавляем middleware для установки локали, проверки режима тех. работ и CSP
        $middleware->web(append: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\AddCspHeaders::class,
            \App\Http\Middleware\CheckPinCode::class,
            \App\Http\Middleware\TrackPartnerAttribution::class,
        ]);

        $middleware->alias([
            'auth.client' => \App\Http\Middleware\AuthenticateClient::class,
            'is.bot' => \App\Http\Middleware\IsBot::class,
            'extension.cors' => \App\Http\Middleware\ExtensionCors::class,
        ]);

        // Исключаем партнёрские cookies из шифрования (нужен доступ из JS)
        $middleware->encryptCookies(except: ['lr_partner_id', 'lr_link_id']);

        // Отключаем CSRF для API расширения, Telegram webhook и payment webhook
        $middleware->validateCsrfTokens(except: [
            'api/extension/*',
            'api/ext-api/*',
            'api/telegram/webhook',
            'api/webhook/payment',
            'api/pin-code/verify',
            'api/partners',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            \Illuminate\Foundation\ViteManifestNotFoundException::class,
        ]);

        // Корректный 429 ответ при срабатывании rate limit
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Слишком много запросов. Попробуйте позже.',
                ], 429);
            }

            abort(429, 'Слишком много запросов. Попробуйте позже.');
        });
    })->create();
