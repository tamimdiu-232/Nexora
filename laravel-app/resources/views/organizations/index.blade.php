@extends('layouts.app')

@section('title', 'Organizations')
@section('page_title', 'Tenant Organizations Management')

@section('content')
<x-page-header title="Organizations" subtitle="Manage tenant organizations, administrative codes, and activation statuses">
    <a href="{{ route('organizations.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Organization
    </a>
</x-page-header>

<div class="nx-card">
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>Organization Name</th>
                    <th>Code</th>
                    <th>Departments</th>
                    <th>Users</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $org)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $org->name }}</div>
                            <small class="text-muted">Registered {{ $org->created_at->format('M d, Y') }}</small>
                        </td>
                        <td><span class="badge bg-light text-dark border font-monospace">{{ $org->code }}</span></td>
                        <td>{{ $org->departments_count }}</td>
                        <td>{{ $org->users_count }}</td>
                        <td>{{ $org->documents_count }}</td>
                        <td>
                            <x-status :status="$org->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="text-end">
                            <a href="{{ route('organizations.edit', $org) }}" class="btn btn-sm btn-light border">
                                <i class="bi bi-pencil me-1"></i>Edit / Suspend
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No organizations registered.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($organizations->hasPages())
        <div class="p-3 border-top">
            {{ $organizations->links() }}
        </div>
    @endif
</div>
@endsection
