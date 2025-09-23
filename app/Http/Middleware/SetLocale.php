<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем язык из сессии или используем дефолтный
        $locale = session('locale', config('app.locale', 'ru'));

        // Проверяем что язык поддерживается
        if (in_array($locale, ['ru', 'en'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
