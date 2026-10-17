<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Self-service reset via Laravel's password broker. With MAIL_MAILER=log the link appears in storage/logs/laravel.log. */
class PasswordResetController extends Controller
{
    public function showRequest() { return view('auth.forgot'); }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => $data['email'], 'is_active' => true]); // inactive accounts get nothing
        AuditLogger::log('auth.password_reset_requested');
        // Same answer whether or not the account exists (no account enumeration).
        return back()->with('status', 'If an active account exists for that email, a reset link has been sent.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);
        $status = Password::reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'], 'is_active' => true],
            function ($user, $password) {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
                AuditLogger::log('auth.password_reset_completed', null, [], $user);
            }
        );
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password updated. You can sign in now.')
            : back()->withErrors(['email' => 'This reset link is invalid or has expired.'])->withInput($request->only('email'));
    }
}
