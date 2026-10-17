<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);
        $u = $request->user();
        $deps = Department::with('organization')->withCount(['users', 'documents'])
            ->when(!$u->isSuper(), fn ($q) => $q->where('organization_id', $u->organization_id))
            ->orderBy('organization_id')->orderBy('name')->paginate(20);
        return view('departments.index', ['departments' => $deps]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Department::class);
        return view('departments.form', ['department' => new Department(['is_active' => true]), 'organizations' => $this->orgs($request->user())]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Department::class);
        $u = $request->user();
        $orgId = $u->isSuper() ? $request->integer('organization_id') : $u->organization_id; // org admins cannot choose another org
        $v = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where('organization_id', $orgId)]]);
        abort_unless($orgId && Organization::whereKey($orgId)->exists(), 422, 'Select an organization.');
        $dept = Department::create(['organization_id' => $orgId, 'name' => $v['name'], 'is_active' => $request->boolean('is_active', true)]);
        AuditLogger::log('department.created', $dept);
        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        Gate::authorize('update', $department);
        return view('departments.form', ['department' => $department, 'organizations' => $this->orgs(auth()->user())]);
    }

    public function update(Request $request, Department $department)
    {
        Gate::authorize('update', $department);
        $v = $request->validate(['name' => ['required', 'string', 'max:255',
            Rule::unique('departments', 'name')->where('organization_id', $department->organization_id)->ignore($department->id)]]);
        $department->update(['name' => $v['name'], 'is_active' => $request->boolean('is_active')]);
        AuditLogger::log('department.updated', $department, ['is_active' => $department->is_active]);
        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    private function orgs($u) { return $u->isSuper() ? Organization::orderBy('name')->get() : Organization::whereKey($u->organization_id)->get(); }
}
