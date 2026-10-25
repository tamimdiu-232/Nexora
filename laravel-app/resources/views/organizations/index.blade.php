@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
<x-page-header title="Organizations" subtitle="Create organizations and control whether they can sign in.">
    <a class="btn btn-primary" href="{{ route('organizations.create') }}"><i class="bi bi-plus-lg me-1"></i> Add organization</a>
</x-page-header>
<div class="nx-card">
    @if($organizations->isEmpty())
        <x-empty icon="bi-buildings" title="No organizations">Add the first organization, then create its Organization Admin.</x-empty>
    @else
    <div class="table-responsive"><table class="nx-table">
        <thead><tr><th>Organization</th><th>Code</th><th>Departments</th><th>Users</th><th>Documents</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($organizations as $o)
            <tr><td class="title">{{ $o->name }}</td><td class="text-muted">{{ $o->code }}</td><td>{{ $o->departments_count }}</td><td>{{ $o->users_count }}</td><td>{{ $o->documents_count }}</td>
                <td><x-status :status="$o->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end"><a class="btn btn-ghost btn-sm" href="{{ route('organizations.edit', $o) }}">Edit</a></td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
<div class="mt-3">{{ $organizations->links('pagination::bootstrap-5') }}</div>
@endsection
