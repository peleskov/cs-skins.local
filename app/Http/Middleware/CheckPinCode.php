<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPinCode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('pin_code_pending')) {
            return $next($request);
        }

        // Страница ввода кода и верификация — пропускаем
        if ($request->is('pin-code', 'api/pin-code/verify')) {
            return $next($request);
        }

        // API-запросы и статика — пропускаем без сброса
        if ($request->expectsJson() || $request->is('api/*', 'build/*', 'css/*', 'js/*', 'images/*', 'fonts/*', 'sounds/*', 'favicon*')) {
            return $next($request);
        }

        // Навигация на другую страницу (GET, не API) — юзер ушёл без ввода кода
        $request->session()->forget(['pin_code_pending', 'pin_code_client_id']);

        return $next($request);
    }
}
