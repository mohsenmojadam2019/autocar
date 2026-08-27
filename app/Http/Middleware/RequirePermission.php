<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /** Rejects unauthenticated or under-privileged users for granular admin operations. */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user()?->is_active, 403);
        abort_unless($request->user()?->hasPermission($permission), 403);
        return $next($request);
    }
}
