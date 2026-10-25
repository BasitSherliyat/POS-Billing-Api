<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$userTypes): Response
    {
        $userType = strtolower(trim((string) $request->user()?->user_type));
        $allowedTypes = array_map(
            static fn (string $type): string => strtolower(trim($type)),
            $userTypes,
        );

        abort_unless(in_array($userType, $allowedTypes, true), 403);

        return $next($request);
    }
}
