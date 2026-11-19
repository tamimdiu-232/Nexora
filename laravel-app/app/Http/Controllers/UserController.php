<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        Gate::authorize('viewAny', User::class);

        $query = User::query()->with(['organization', 'department']);

        if ($user->isSuperAdmin()) {
            // Super Admin manages Organization Admins
            $query->where('role', 'org_admin');
        } elseif ($user->isOrgAdmin()) {
            // Org Admin manages own organization
            $query->where('organization_id', $user->organization_id)
                  ->where('role', '!=', 'super_admin');
        } elseif ($user->isDeptAdmin()) {
            // Dept Admin manages own department
            $query->where('organization_id', $user->organization_id)
                  ->where('department_id', $user->department_id)
                  ->whereIn('role', ['officer', 'reviewer', 'viewer']);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $currentUser = Auth::user();
        Gate::authorize('create', User::class);

        $organizations = [];
        $departments = [];
        $allowedRoles = [];

        if ($currentUser->isSuperAdmin()) {
            $organizations = Organization::active()->get();
            $allowedRoles = ['org_admin' => 'Organization Administrator'];
        } elseif ($currentUser->isOrgAdmin()) {
            $departments = Department::where('organization_id', $currentUser->organization_id)->active()->get();
            $allowedRoles = [
                'org_admin' => 'Organization Administrator',
                'dept_admin' => 'Department Administrator',
                'officer' => 'Document Officer',
                'reviewer' => 'Human Reviewer',
                'viewer' => 'Document Viewer',
            ];
        } elseif ($currentUser->isDeptAdmin()) {
            $departments = Department::where('id', $currentUser->department_id)->get();
            $allowedRoles = [
                'officer' => 'Document Officer',
                'reviewer' => 'Human Reviewer',
                'viewer' => 'Document Viewer',
            ];
        }

        return view('users.create', compact('organizations', 'departments', 'allowedRoles'));
    }

    public function store(Request $request)
    {
        $currentUser = Auth::user();
        Gate::authorize('create', User::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', PasswordRule::min(8)],
            'role' => ['required', 'string'],
            'is_active' => ['boolean'],
        ];

        if ($currentUser->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'exists:organizations,id'];
            $rules['role'] = ['required', Rule::in(['org_admin'])];
        } elseif ($currentUser->isOrgAdmin()) {
            $rules['role'] = ['required', Rule::in(['org_admin', 'dept_admin', 'officer', 'reviewer', 'viewer'])];
            $rules['department_id'] = ['nullable', 'exists:departments,id'];
        } elseif ($currentUser->isDeptAdmin()) {
            $rules['role'] = ['required', Rule::in(['officer', 'reviewer', 'viewer'])];
        }

        $validated = $request->validate($rules);

        // Determine org and dept
        $orgId = null;
        $deptId = null;

        if ($currentUser->isSuperAdmin()) {
            $orgId = $validated['organization_id'];
            $deptId = null; // Org admin has no specific department
        } elseif ($currentUser->isOrgAdmin()) {
            $orgId = $currentUser->organization_id;
            // Dept required for dept_admin and officer
            if (in_array($validated['role'], ['dept_admin', 'officer'], true) && empty($validated['department_id'])) {
                return back()->withInput()->withErrors(['department_id' => 'Department is required for this role.']);
            }
            $deptId = $validated['department_id'] ?? null;
        } elseif ($currentUser->isDeptAdmin()) {
            $orgId = $currentUser->organization_id;
            $deptId = $currentUser->department_id;
        }

        $newUser = User::create([
            'organization_id' => $orgId,
            'department_id' => $deptId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('user.created', $newUser, [
            'target_user_id' => $newUser->id,
            'role' => $newUser->role,
            'organization_id' => $newUser->organization_id,
        ]);

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $currentUser = Auth::user();
        Gate::authorize('update', $user);

        $departments = [];
        $allowedRoles = [];

        if ($currentUser->isSuperAdmin()) {
            $allowedRoles = ['org_admin' => 'Organization Administrator'];
        } elseif ($currentUser->isOrgAdmin()) {
            $departments = Department::where('organization_id', $currentUser->organization_id)->active()->get();
            $allowedRoles = [
                'org_admin' => 'Organization Administrator',
                'dept_admin' => 'Department Administrator',
                'officer' => 'Document Officer',
                'reviewer' => 'Human Reviewer',
                'viewer' => 'Document Viewer',
            ];
        } elseif ($currentUser->isDeptAdmin()) {
            $departments = Department::where('id', $currentUser->department_id)->get();
            $allowedRoles = [
                'officer' => 'Document Officer',
                'reviewer' => 'Human Reviewer',
                'viewer' => 'Document Viewer',
            ];
        }

        return view('users.edit', compact('user', 'departments', 'allowedRoles'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();
        Gate::authorize('update', $user);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'password' => ['nullable', PasswordRule::min(8)],
        ];

        $validated = $request->validate($rules);

        $oldRole = $user->role;
        $newRole = $validated['role'];

        $user->name = $validated['name'];
        $user->role = $newRole;
        $user->is_active = $request->boolean('is_active');

        if ($currentUser->isOrgAdmin() && array_key_exists('department_id', $validated)) {
            $user->department_id = $validated['department_id'];
        }

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        AuditLogger::log('user.updated', $user, [
            'role_changed' => ($oldRole !== $newRole),
            'from_role' => $oldRole,
            'to_role' => $newRole,
            'is_active' => $user->is_active,
        ]);

        return redirect()->route('users.index')->with('status', 'User details updated.');
    }
}
