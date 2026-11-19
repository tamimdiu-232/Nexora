<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Organization::class);

        $organizations = Organization::withCount(['departments', 'users', 'documents'])
            ->latest()
            ->paginate(15);

        return view('organizations.index', compact('organizations'));
    }

    public function create()
    {
        Gate::authorize('create', Organization::class);

        return view('organizations.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:organizations,code'],
            'is_active' => ['boolean'],
        ]);

        $org = Organization::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('organization.created', $org, [
            'name' => $org->name,
            'code' => $org->code,
        ]);

        return redirect()->route('organizations.index')->with('status', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        Gate::authorize('update', $organization);

        return view('organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        Gate::authorize('update', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('organizations')->ignore($organization->id)],
            'is_active' => ['boolean'],
        ]);

        $oldActive = $organization->is_active;
        $newActive = $request->boolean('is_active');

        $organization->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $newActive,
        ]);

        AuditLogger::log('organization.updated', $organization, [
            'is_active_changed' => ($oldActive !== $newActive),
            'is_active' => $newActive,
        ]);

        return redirect()->route('organizations.index')->with('status', 'Organization updated successfully.');
    }
}
