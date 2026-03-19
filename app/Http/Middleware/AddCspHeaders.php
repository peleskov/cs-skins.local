<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

class AddCspHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = Str::random(32);
        app()->instance('csp-nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // Не применяем CSP к админке Filament и Livewire — у них свои скрипты и стили
        if ($request->is('livewire/*') || str_contains($request->path(), 'livewire')) {
            return $response;
        }
        foreach (\Filament\Facades\Filament::getPanels() as $panel) {
            if ($request->is($panel->getPath(), $panel->getPath() . '/*')) {
                return $response;
            }
        }

        if (method_exists($response, 'header')) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' https://*.yandex.ru https://*.yandex.com https://yastatic.net",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: https://*.steamstatic.com https://*.yandex.ru https://*.yandex.com https://steamcdn-a.akamaihd.net",
                "connect-src 'self' wss://{$request->getHost()} https://*.yandex.ru https://*.yandex.com https://yastatic.net",
                "frame-src https://payment.arcopay.tech https://qr.nspk.ru",
                "object-src 'none'",
                "base-uri 'self'",
            ]);

            $response->header('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
