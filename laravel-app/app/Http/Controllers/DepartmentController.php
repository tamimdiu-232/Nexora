<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        Gate::authorize('viewAny', Department::class);

        $departments = Department::where('organization_id', $user->organization_id)
            ->withCount(['users', 'documents'])
            ->latest()
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        Gate::authorize('create', Department::class);

        return view('departments.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        Gate::authorize('create', Department::class);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(fn ($q) => $q->where('organization_id', $user->organization_id)),
            ],
            'is_active' => ['boolean'],
        ]);

        $dept = Department::create([
            'organization_id' => $user->organization_id, // always inside own org
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('department.created', $dept, [
            'name' => $dept->name,
        ]);

        return redirect()->route('departments.index')->with('status', 'Department created successfully.');
    }

    public function edit(Department $department)
    {
        Gate::authorize('update', $department);

        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $user = Auth::user();
        Gate::authorize('update', $department);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')
                    ->where(fn ($q) => $q->where('organization_id', $user->organization_id))
                    ->ignore($department->id),
            ],
            'is_active' => ['boolean'],
        ]);

        $department->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('department.updated', $department, [
            'name' => $department->name,
            'is_active' => $department->is_active,
        ]);

        return redirect()->route('departments.index')->with('status', 'Department updated successfully.');
    }
}
