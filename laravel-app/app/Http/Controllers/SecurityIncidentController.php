<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SecurityIncident;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Security incident register plus a feed of security-relevant events. */
class SecurityIncidentController extends Controller
{
    public function index()
    {
        $events = AuditLog::with('user')->latest('created_at')->latest('id')
            ->where(fn ($q) => $q->whereIn('action', ['auth.login_failed', 'user.role_changed', 'user.deactivated', 'auth.password_reset_requested'])
                ->orWhere('action', 'like', 'admin.%')->orWhere('action', 'like', 'incident.%'))
            ->limit(15)->get();

        $failedLogins = AuditLog::where('action', 'auth.login_failed')->where('created_at', '>=', now()->subDay())->get();

        return view('admin.incidents', [
            'incidents' => SecurityIncident::with(['organization', 'reporter'])->orderByRaw("status = 'resolved'")->latest()->paginate(10),
            'organizations' => Organization::orderBy('name')->get(),
            'events' => $events,
            'failedLoginCount' => $failedLogins->count(),
            'topTargets' => $failedLogins->groupBy(fn ($l) => $l->metadata['email'] ?? 'unknown')->map->count()->sortDesc()->take(3),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(SecurityIncident::SEVERITIES)],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);
        $incident = SecurityIncident::create($data + ['reported_by' => $request->user()->id, 'status' => 'open']);
        AuditLogger::log('incident.created', $incident, ['severity' => $incident->severity]);
        return back()->with('success', 'Incident recorded.');
    }

    public function update(Request $request, SecurityIncident $incident)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(SecurityIncident::STATUSES)],
            'resolution_note' => ['nullable', 'string', 'max:5000', 'required_if:status,resolved'],
        ]);
        $old = $incident->status;
        $incident->update($data + ['resolved_at' => $data['status'] === 'resolved' ? now() : null]);
        AuditLogger::log('incident.updated', $incident, ['from' => $old, 'to' => $incident->status]);
        return back()->with('success', 'Incident updated.');
    }
}
