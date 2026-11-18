@extends('layouts.app')

@section('title', 'Departments')
@section('page_title', 'Department Administration')

@section('content')
<x-page-header title="Organization Departments" subtitle="Manage internal divisions and departmental clearance boundaries">
    <a href="{{ route('departments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Department
    </a>
</x-page-header>

<div class="nx-card">
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>Department Name</th>
                    <th>Members</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $dept->name }}</div>
                            <small class="text-muted">Created {{ $dept->created_at->format('M d, Y') }}</small>
                        </td>
                        <td>{{ $dept->users_count }} member(s)</td>
                        <td>{{ $dept->documents_count }} document(s)</td>
                        <td>
                            <x-status :status="$dept->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="text-end">
                            <a href="{{ route('departments.edit', $dept) }}" class="btn btn-sm btn-light border">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No departments configured for this organization.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($departments->hasPages())
        <div class="p-3 border-top">
            {{ $departments->links() }}
        </div>
    @endif
</div>
@endsection
