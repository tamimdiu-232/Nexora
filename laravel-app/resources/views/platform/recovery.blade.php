@extends('layouts.app')

@section('title', 'Account Recovery')
@section('page_title', 'Account Recovery & Session Management')

@section('content')
<x-page-header title="Account Recovery" subtitle="Manage account access, dispatch reset links, and revoke active sessions for security response" />

<div class="nx-card mb-4">
    <div class="nx-card-header">
        <form method="GET" action="{{ route('platform.recovery') }}" class="d-flex gap-2 w-100 max-w-md">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by user name or email..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
            @if(request('search'))
                <a href="{{ route('platform.recovery') }}" class="btn btn-sm btn-link text-muted">Clear</a>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Organization & Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Recovery Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $targetUser)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $targetUser->name }}</div>
                            <div class="small text-muted">{{ $targetUser->email }}</div>
                        </td>
                        <td>
                            <div>{{ $targetUser->organization?->name ?? 'Platform Operations' }}</div>
                            @if($targetUser->department)
                                <small class="text-muted">{{ $targetUser->department->name }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">
                                {{ config('nexora.roles.' . $targetUser->role, $targetUser->role) }}
                            </span>
                        </td>
                        <td>
                            <x-status :status="$targetUser->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <form method="POST" action="{{ route('platform.recovery.reset_link', $targetUser) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-light border" title="Dispatch password reset link" data-confirm="Send password reset link to {{ $targetUser->email }}?">
                                        <i class="bi bi-envelope me-1"></i>Reset Link
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('platform.recovery.revoke_sessions', $targetUser) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-light border text-warning" title="Revoke all active browser sessions" data-confirm="Revoke all active sessions for {{ $targetUser->name }}?">
                                        <i class="bi bi-shield-x me-1"></i>Revoke Sessions
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('platform.recovery.toggle_status', $targetUser) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-light border {{ $targetUser->is_active ? 'text-danger' : 'text-success' }}" data-confirm="Are you sure you want to {{ $targetUser->is_active ? 'deactivate' : 'activate' }} this user?">
                                        <i class="bi {{ $targetUser->is_active ? 'bi-lock' : 'bi-unlock' }} me-1"></i>
                                        {{ $targetUser->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No accounts match search criteria.</td>
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
