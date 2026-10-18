@extends('layouts.app')
@section('title', auth()->user()->isSuper() ? 'Organization admins' : 'Users')
@section('content')
<x-page-header :title="auth()->user()->isSuper() ? 'Organization admins' : 'Users'" subtitle="Create accounts and control who can do what.">
    <a class="btn btn-primary" href="{{ route('users.create') }}"><i class="bi bi-person-plus me-1"></i> Add {{ auth()->user()->isSuper() ? 'organization admin' : 'user' }}</a>
</x-page-header>
<form class="mb-3" method="GET"><div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search name or email"><button class="btn btn-ghost">Search</button></div></form>
<div class="nx-card">
    @if($users->isEmpty())
        <x-empty icon="bi-people" title="No accounts yet">Use “Add” to create the first one.</x-empty>
    @else
    <div class="table-responsive"><table class="nx-table">
        <thead><tr><th>Name</th><th>Role</th><th>Organization</th><th>Department</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td><div class="title">{{ $u->name }}</div><div class="small text-muted">{{ $u->email }}</div></td>
                <td>{{ $u->roleLabel() }}</td><td>{{ $u->organization?->name ?? '-' }}</td><td>{{ $u->department?->name ?? '-' }}</td>
                <td><x-status :status="$u->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end">@can('update', $u)<a class="btn btn-ghost btn-sm" href="{{ route('users.edit', $u) }}">Edit</a>@endcan</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
