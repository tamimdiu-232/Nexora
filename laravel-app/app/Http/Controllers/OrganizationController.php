<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Global administration: Super Admin only (route middleware + policy). */
class OrganizationController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Organization::class);
        return view('organizations.index', ['organizations' => Organization::withCount(['departments', 'users', 'documents'])->orderBy('name')->paginate(15)]);
    }

    public function create() { Gate::authorize('create', Organization::class); return view('organizations.form', ['organization' => new Organization(['is_active' => true])]); }

    public function store(Request $request)
    {
        Gate::authorize('create', Organization::class);
        $org = Organization::create($this->data($request));
        AuditLogger::log('organization.created', $org);
        return redirect()->route('organizations.index')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization) { Gate::authorize('update', $organization); return view('organizations.form', ['organization' => $organization]); }

    public function update(Request $request, Organization $organization)
    {
        Gate::authorize('update', $organization);
        $organization->update($this->data($request, $organization));
        AuditLogger::log('organization.updated', $organization, ['is_active' => $organization->is_active]);
        return redirect()->route('organizations.index')->with('success', 'Organization updated.');
    }

    private function data(Request $request, ?Organization $org = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($org?->id)],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('organizations', 'code')->ignore($org?->id)],
        ]);
        return $v + ['is_active' => $request->boolean('is_active')];
    }
}
