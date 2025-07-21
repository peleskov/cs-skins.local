<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем, что это действительно запрос от расширения
        $origin = $request->header('Origin');
        $isExtensionRequest = $origin && str_starts_with($origin, 'chrome-extension://');
        
        // Логируем только запросы от расширений
        if ($isExtensionRequest) {
            \Log::info('Extension API request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'origin' => $origin
            ]);
        }

        // Для preflight OPTIONS запросов
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Добавляем CORS заголовки к ответу
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
            ->header('Access-Control-Max-Age', '86400');
    }
}