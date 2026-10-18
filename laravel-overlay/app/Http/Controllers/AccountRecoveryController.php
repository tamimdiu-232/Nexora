<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Account recovery for platform security staff. Works on accounts only - never on documents or chats. */
class AccountRecoveryController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('organization')->where('id', '!=', $request->user()->id)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%' . $request->input('q') . '%')->orWhere('email', 'like', '%' . $request->input('q') . '%')))
            ->orderBy('name')->paginate(15)->withQueryString();
        return view('admin.accounts', ['users' => $users]);
    }

    public function sendResetLink(User $user)
    {
        Gate::authorize('recover', $user);
        Password::sendResetLink(['email' => $user->email, 'is_active' => true]);
        AuditLogger::log('admin.reset_link_sent', $user);
        return back()->with('success', 'A password reset link was sent to the account owner.');
    }

    public function revokeSessions(User $user)
    {
        Gate::authorize('recover', $user);
        $this->endSessions($user);
        AuditLogger::log('admin.sessions_revoked', $user);
        return back()->with('success', 'All sessions of this account were signed out.');
    }

    public function toggleActive(User $user)
    {
        Gate::authorize('recover', $user);
        $user->update(['is_active' => !$user->is_active]);
        if (!$user->is_active) $this->endSessions($user);
        AuditLogger::log($user->is_active ? 'admin.user_activated' : 'admin.user_deactivated', $user);
        return back()->with('success', $user->is_active ? 'Account re-activated.' : 'Account deactivated and signed out.');
    }

    /** Rotates the remember-me token and, with database sessions, removes the user's live sessions. */
    private function endSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
