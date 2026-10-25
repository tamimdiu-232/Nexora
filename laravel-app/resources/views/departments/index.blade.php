@extends('layouts.app')
@section('title', 'Departments')
@section('content')
<x-page-header title="Departments" subtitle="Departments decide who can see which documents.">
    <a class="btn btn-primary" href="{{ route('departments.create') }}"><i class="bi bi-plus-lg me-1"></i> Add department</a>
</x-page-header>
<div class="nx-card">
    @if($departments->isEmpty())
        <x-empty icon="bi-diagram-3" title="No departments yet">Add your first department.</x-empty>
    @else
    <div class="table-responsive"><table class="nx-table">
        <thead><tr><th>Department</th><th>Users</th><th>Documents</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($departments as $d)
            <tr><td class="title">{{ $d->name }}</td><td>{{ $d->users_count }}</td><td>{{ $d->documents_count }}</td>
                <td><x-status :status="$d->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end">@can('update', $d)<a class="btn btn-ghost btn-sm" href="{{ route('departments.edit', $d) }}">Edit</a>@endcan</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
<div class="mt-3">{{ $departments->links('pagination::bootstrap-5') }}</div>
@endsection
