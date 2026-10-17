<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && (!$user->is_active || ($user->organization && !$user->organization->is_active && !$user->isSuper()))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'This account is deactivated.']);
        }
        return $next($request);
    }
}
