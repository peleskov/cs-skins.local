<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsBot
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('client')->check() || !Auth::guard('client')->user()->is_bot) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
