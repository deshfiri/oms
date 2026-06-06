<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            abort(403, 'Inactive user');
        }
        if ($user->isAdmin()) {
            return $next($request);
        }
        if ($roles && ! in_array($user->role, $roles, true)) {
            abort(403, 'Insufficient role');
        }
        return $next($request);
    }
}
