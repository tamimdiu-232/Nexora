<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Departments belong to an organization and are managed by that organization's own admin. */
class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);
        $departments = Department::withCount(['users', 'documents'])
            ->where('organization_id', $request->user()->organization_id)
            ->orderBy('name')->paginate(20);
        return view('departments.index', ['departments' => $departments]);
    }

    public function create()
    {
        Gate::authorize('create', Department::class);
        return view('departments.form', ['department' => new Department(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Department::class);
        $orgId = $request->user()->organization_id; // always the admin's own organization, whatever the form says
        $data = $request->validate(['name' => ['required', 'string', 'max:255',
            Rule::unique('departments', 'name')->where('organization_id', $orgId)]]);

        $department = Department::create(['organization_id' => $orgId, 'name' => $data['name'], 'is_active' => $request->boolean('is_active', true)]);
        AuditLogger::log('department.created', $department);
        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        Gate::authorize('update', $department);
        return view('departments.form', ['department' => $department]);
    }

    public function update(Request $request, Department $department)
    {
        Gate::authorize('update', $department);
        $data = $request->validate(['name' => ['required', 'string', 'max:255',
            Rule::unique('departments', 'name')->where('organization_id', $department->organization_id)->ignore($department->id)]]);

        $department->update(['name' => $data['name'], 'is_active' => $request->boolean('is_active')]);
        AuditLogger::log('department.updated', $department, ['is_active' => $department->is_active]);
        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }
}
