@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="d-flex justify-content-between mb-3"><h3 class="mb-0">Users</h3><a class="btn btn-primary" href="{{ route('users.create') }}">Add user</a></div>
<form class="mb-3" method="GET"><div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name or email"><button class="btn btn-outline-secondary">Search</button></div></form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Organization</th><th>Department</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($users as $u)
<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->roleLabel() }}</td><td>{{ $u->organization?->name ?? '-' }}</td><td>{{ $u->department?->name ?? '-' }}</td>
<td>@if($u->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
<td>@can('update', $u)<a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $u) }}">Edit</a>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-3">No users.</td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
