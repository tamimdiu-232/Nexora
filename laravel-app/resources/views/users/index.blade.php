@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'User Administration')

@section('content')
<x-page-header title="User Management" subtitle="Manage user accounts, institutional roles, and clearance boundaries">
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>New User
    </a>
</x-page-header>

<div class="nx-card mb-4">
    <div class="nx-card-header">
        <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2 max-w-md w-100">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or email..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
            @if(request('search'))
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Organization & Department</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $u->name }}</div>
                            <small class="text-muted">{{ $u->email }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">
                                {{ config('nexora.roles.' . $u->role, $u->role) }}
                            </span>
                        </td>
                        <td>
                            <div>{{ $u->organization?->name ?? 'Platform Operations' }}</div>
                            @if($u->department)
                                <small class="text-muted">{{ $u->department->name }}</small>
                            @endif
                        </td>
                        <td>
                            <x-status :status="$u->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="small text-muted">{{ $u->created_at->format('M d, Y') }}</td>
                        <td class="text-end">
                            @if((int) Auth::id() !== (int) $u->id)
                                <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light border">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            @else
                                <span class="badge bg-light text-muted border">You</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
        <div class="p-3 border-top">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
