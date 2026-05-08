<?php

namespace App\Http\Middleware;

use App\Services\OnlineCounterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOnlineUsers
{
    public function __construct(private OnlineCounterService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->is('api/online')) {
            $key = auth('client')->check()
                ? 'u:'.auth('client')->id()
                : 's:'.$request->session()->getId();

            try {
                $this->service->track($key);
            } catch (\Throwable $e) {
                // Не ломаем запрос, если Redis недоступен
            }
        }

        return $next($request);
    }
}
