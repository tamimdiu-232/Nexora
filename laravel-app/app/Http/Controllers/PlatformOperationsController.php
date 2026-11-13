<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\ProcessingJob;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AiClient;
use App\Services\AuditLogger;
use App\Services\LogReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlatformOperationsController extends Controller
{
    public function dashboard(AiClient $aiClient)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403, 'Access denied to platform plane.');
        }

        $activeOrgs = Organization::where('is_active', true)->count();
        $totalOrgs = Organization::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalDocs = Document::count();
        $openIncidents = SecurityIncident::whereIn('status', ['open', 'investigating'])->count();

        // Failed logins in last 24 hours
        $failedLogins24h = AuditLog::where('action', 'auth.failed_login')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $aiHealth = $aiClient->health();

        // Recent administrative activity (never document contents)
        $recentAdminActivity = AuditLog::whereIn('action', [
            'auth.login', 'auth.failed_login', 'organization.created', 'organization.updated',
            'user.created', 'user.updated', 'admin.sessions_revoked', 'admin.user_deactivated',
            'incident.created', 'incident.updated'
        ])->latest('created_at')->take(8)->get();

        return view('platform.dashboard', compact(
            'activeOrgs',
            'totalOrgs',
            'activeUsers',
            'totalDocs',
            'openIncidents',
            'failedLogins24h',
            'aiHealth',
            'recentAdminActivity'
        ));
    }

    public function health(AiClient $aiClient)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $aiStatus = $aiClient->health();

        // Database status
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Exception $e) {
            $dbOk = false;
        }

        // Storage writable
        $storageWritable = false;
        try {
            $testFile = 'health_check_' . time() . '.txt';
            Storage::disk('private')->put($testFile, 'test');
            $storageWritable = Storage::disk('private')->exists($testFile);
            Storage::disk('private')->delete($testFile);
        } catch (\Exception $e) {
            $storageWritable = false;
        }

        // Processing status counts
        $processingCounts = Document::selectRaw('processing_status, count(*) as count')
            ->groupBy('processing_status')
            ->pluck('count', 'processing_status')
            ->all();

        // Recent processing failures per organization without document titles
        $recentFailures = ProcessingJob::where('status', 'failed')
            ->with(['document.organization'])
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($job) {
                return [
                    'organization' => $job->document?->organization?->name ?? 'Unknown',
                    'error_code' => $job->error_code,
                    'error_message' => $job->error_message,
                    'failed_at' => $job->finished_at,
                ];
            });

        // Error logs via LogReader
        $errorLogs = LogReader::readSanitizedErrorLogs(40);

        return view('platform.health', compact(
            'aiStatus',
            'dbOk',
            'storageWritable',
            'processingCounts',
            'recentFailures',
            'errorLogs'
        ));
    }

    public function recovery(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $query = User::where('id', '!=', $user->id)->with('organization');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('platform.recovery', compact('users'));
    }

    public function sendRecoveryResetLink(User $targetUser)
    {
        Gate::authorize('recover', $targetUser);

        Password::broker()->sendResetLink(['email' => $targetUser->email]);

        AuditLogger::log('admin.reset_link_sent', $targetUser, [
            'target_user_id' => $targetUser->id,
            'target_email' => $targetUser->email,
        ]);

        return back()->with('status', "Password reset link sent to {$targetUser->email}.");
    }

    public function revokeSessions(User $targetUser)
    {
        Gate::authorize('recover', $targetUser);

        // Rotate remember token
        $targetUser->setRememberToken(Str::random(60));
        $targetUser->save();

        // Delete active sessions from sessions table if driver is database
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $targetUser->id)->delete();
        }

        AuditLogger::log('admin.sessions_revoked', $targetUser, [
            'target_user_id' => $targetUser->id,
        ]);

        return back()->with('status', "Active sessions revoked for {$targetUser->name}.");
    }

    public function toggleUserStatus(User $targetUser)
    {
        Gate::authorize('recover', $targetUser);

        $newStatus = !$targetUser->is_active;
        $targetUser->is_active = $newStatus;

        // If deactivating, also revoke active sessions immediately
        if (!$newStatus) {
            $targetUser->setRememberToken(Str::random(60));
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $targetUser->id)->delete();
            }
        }

        $targetUser->save();

        $action = $newStatus ? 'admin.user_activated' : 'admin.user_deactivated';
        AuditLogger::log($action, $targetUser, [
            'target_user_id' => $targetUser->id,
            'is_active' => $newStatus,
        ]);

        $label = $newStatus ? 'activated' : 'deactivated';
        return back()->with('status', "User {$targetUser->name} has been {$label}.");
    }

    public function incidents()
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $incidents = SecurityIncident::with(['organization', 'reporter'])
            ->latest()
            ->paginate(15);

        $organizations = Organization::active()->get();

        // Security events feed from audit logs
        $securityFeed = AuditLog::whereIn('action', [
            'auth.failed_login', 'admin.sessions_revoked', 'admin.user_deactivated', 'user.updated'
        ])->latest('created_at')->take(10)->get();

        return view('platform.incidents', compact('incidents', 'organizations', 'securityFeed'));
    }

    public function storeIncident(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
        ]);

        $incident = SecurityIncident::create([
            'reported_by' => $user->id,
            'organization_id' => $validated['organization_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'severity' => $validated['severity'],
            'status' => 'open',
        ]);

        AuditLogger::log('incident.created', $incident, [
            'incident_id' => $incident->id,
            'severity' => $incident->severity,
        ]);

        return back()->with('status', 'Security incident recorded.');
    }

    public function updateIncident(Request $request, SecurityIncident $incident)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:open,investigating,resolved'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        // Resolution note required to close / resolve an incident
        if ($validated['status'] === 'resolved' && empty($validated['resolution_note'])) {
            return back()->withErrors(['resolution_note' => 'A resolution note is required to mark an incident as resolved.']);
        }

        $incident->status = $validated['status'];
        if ($validated['status'] === 'resolved') {
            $incident->resolution_note = $validated['resolution_note'];
            $incident->resolved_at = now();
        } else {
            $incident->resolution_note = $validated['resolution_note'] ?? $incident->resolution_note;
        }

        $incident->save();

        AuditLogger::log('incident.updated', $incident, [
            'incident_id' => $incident->id,
            'status' => $incident->status,
        ]);

        return back()->with('status', 'Incident status updated.');
    }
}
