@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
<div class="d-flex justify-content-between mb-3"><h3 class="mb-0">Organizations</h3><a class="btn btn-primary" href="{{ route('organizations.create') }}">Add organization</a></div>
<div class="card"><table class="table mb-0 align-middle"><thead><tr><th>Name</th><th>Code</th><th>Depts</th><th>Users</th><th>Docs</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($organizations as $o)<tr><td>{{ $o->name }}</td><td>{{ $o->code }}</td><td>{{ $o->departments_count }}</td><td>{{ $o->users_count }}</td><td>{{ $o->documents_count }}</td>
<td>@if($o->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('organizations.edit', $o) }}">Edit</a></td></tr>
@empty<tr><td colspan="7" class="text-muted text-center py-3">None.</td></tr>@endforelse</tbody></table></div>
<div class="mt-3">{{ $organizations->links('pagination::bootstrap-5') }}</div>
@endsection
