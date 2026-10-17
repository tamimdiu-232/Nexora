<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($cred['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.'])->onlyInput('email');
        }
        if (Auth::attempt(['email' => $cred['email'], 'password' => $cred['password'], 'is_active' => true])) {
            $request->session()->regenerate();
            RateLimiter::clear($key);
            AuditLogger::log('auth.login');
            return redirect()->intended(route('dashboard'));
        }
        RateLimiter::hit($key, 60);
        AuditLogger::log('auth.login_failed', null, ['email' => Str::lower($cred['email'])]); // no password is ever logged
        return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLogger::log('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
