<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);
        $users = User::manageableBy($request->user())->with(['organization', 'department'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $request->input('q') . '%')->orWhere('email', 'like', '%' . $request->input('q') . '%')))
            ->orderBy('name')->paginate(15)->withQueryString();
        return view('users.index', ['users' => $users]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', User::class);
        return view('users.form', $this->formData($request->user(), new User(['is_active' => true])));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);
        $actor = $request->user();
        $data = $this->validated($request, $actor);
        $user = User::create($data + ['password' => $request->input('password')]);
        AuditLogger::log('user.created', $user, ['role' => $user->role]);
        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        Gate::authorize('update', $user);
        return view('users.form', $this->formData($request->user(), $user));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);
        $actor = $request->user();
        $data = $this->validated($request, $actor, $user);
        $oldRole = $user->role; $oldActive = $user->is_active;
        $user->fill($data);
        if ($request->filled('password')) $user->password = $request->input('password');
        $user->save();

        if ($oldRole !== $user->role) AuditLogger::log('user.role_changed', $user, ['from' => $oldRole, 'to' => $user->role]);
        if ($oldActive !== $user->is_active) AuditLogger::log($user->is_active ? 'user.activated' : 'user.deactivated', $user);
        AuditLogger::log('user.updated', $user);
        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    private function formData(User $actor, User $user): array
    {
        return ['user' => $user, 'roles' => collect(config('govdoc.roles'))->only($actor->assignableRoles()),
            'organizations' => $actor->isSuper() ? Organization::orderBy('name')->get() : collect(),
            'departments' => $actor->isSuper() ? Department::with('organization')->orderBy('name')->get()
                : Department::where('organization_id', $actor->organization_id)
                    ->when($actor->isDeptAdmin(), fn ($q) => $q->where('id', $actor->department_id))->get()];
    }

    /** Role, organization and department are decided here on the server, whatever the form sent. */
    private function validated(Request $request, User $actor, ?User $existing = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in($actor->assignableRoles())],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $orgId = $actor->isSuper() ? ($v['organization_id'] ?? null) : $actor->organization_id;
        $deptId = $actor->isDeptAdmin() ? $actor->department_id : ($v['department_id'] ?? null);
        if ($v['role'] === 'super_admin') { $orgId = null; $deptId = null; }
        elseif (!$orgId) throw ValidationException::withMessages(['organization_id' => 'Select an organization.']);
        if ($deptId && !Department::where('id', $deptId)->where('organization_id', $orgId)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Department does not belong to the organization.']);
        }
        if (in_array($v['role'], ['dept_admin', 'officer'], true) && !$deptId) {
            throw ValidationException::withMessages(['department_id' => 'This role requires a department.']);
        }
        return ['name' => $v['name'], 'email' => $v['email'], 'role' => $v['role'], 'organization_id' => $orgId,
            'department_id' => $deptId, 'is_active' => $request->boolean('is_active')];
    }
}
