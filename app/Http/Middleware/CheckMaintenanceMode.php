<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Пропускаем админку, livewire и filament
        if ($request->is('admin*') || $request->is('livewire*') || $request->is('filament*')) {
            return $next($request);
        }

        // Проверяем режим тех. работ
        if (SiteSetting::get('maintenance_mode', false)) {
            // Для API возвращаем JSON
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Сайт на техническом обслуживании! Приносим свои извинения, пожалуйста, зайдите позже.',
                    'maintenance' => true
                ], 503);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
