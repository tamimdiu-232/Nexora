@extends('layouts.app')
@section('title', 'Departments')
@section('content')
<div class="d-flex justify-content-between mb-3"><h3 class="mb-0">Departments</h3><a class="btn btn-primary" href="{{ route('departments.create') }}">Add department</a></div>
<div class="card"><table class="table mb-0 align-middle"><thead><tr><th>Name</th><th>Organization</th><th>Users</th><th>Docs</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($departments as $d)<tr><td>{{ $d->name }}</td><td>{{ $d->organization->name }}</td><td>{{ $d->users_count }}</td><td>{{ $d->documents_count }}</td>
<td>@if($d->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
<td>@can('update', $d)<a class="btn btn-sm btn-outline-primary" href="{{ route('departments.edit', $d) }}">Edit</a>@endcan</td></tr>
@empty<tr><td colspan="6" class="text-muted text-center py-3">None.</td></tr>@endforelse</tbody></table></div>
<div class="mt-3">{{ $departments->links('pagination::bootstrap-5') }}</div>
@endsection
