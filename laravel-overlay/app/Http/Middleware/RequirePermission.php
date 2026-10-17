<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission($permission), 403, 'You do not have permission to do this.');
        return $next($request);
    }
}
