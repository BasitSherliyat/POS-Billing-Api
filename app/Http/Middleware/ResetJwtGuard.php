<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetJwtGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        Auth::forgetGuards();
        $jwt = app('tymon.jwt')->setRequest($request);
        $token = $request->bearerToken();

        if ($token === null) {
            $jwt->unsetToken();
        } else {
            $jwt->setToken($token);
        }

        return $next($request);
    }
}
