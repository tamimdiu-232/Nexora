@extends('layouts.app')

@section('title', 'Security Incidents')
@section('page_title', 'Security Incident Management')

@section('content')
<x-page-header title="Security Incidents" subtitle="Record threat alerts, investigate security anomalies, and document resolutions" />

<div class="row g-4 mb-4">
    <!-- Log New Incident -->
    <div class="col-lg-5">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Record New Incident</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('platform.incidents.store') }}" data-loading>
                    @csrf

                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold text-secondary">Incident Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Repeated brute-force attempts on admin">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <label for="severity" class="form-label small fw-semibold text-secondary">Severity</label>
                            <select class="form-select @error('severity') is-invalid @enderror" id="severity" name="severity" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="organization_id" class="form-label small fw-semibold text-secondary">Organization (Optional)</label>
                            <select class="form-select" id="organization_id" name="organization_id">
                                <option value="">-- Platform Wide --</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" required placeholder="Describe observed activity. WARNING: Do NOT paste credentials or secrets."></textarea>
                        <div class="form-text text-danger small"><i class="bi bi-shield-exclamation me-1"></i>Do not paste passwords, secret keys, or private tokens.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Record Incident
                    </button>
                </form>
            </div>
        </div>

        <!-- Security Event Feed -->
        <div class="nx-card mt-4">
            <div class="nx-card-header">
                <span>Recent Security Event Signals</span>
            </div>
            <div class="nx-card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($securityFeed as $secEvent)
                        <div class="list-group-item py-2 px-3 small">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-light text-danger border">{{ $secEvent->action }}</span>
                                <span class="text-muted">{{ $secEvent->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="text-secondary mt-1">IP: {{ $secEvent->ip_address }} &bull; User ID: {{ $secEvent->user_id ?? 'N/A' }}</div>
                        </div>
                    @empty
                        <div class="p-3 text-center text-muted small">No security anomalies flagged in the window.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Incidents Table -->
    <div class="col-lg-7">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Incidents Register</span>
            </div>
            <div class="nx-card-body p-0">
                <div class="table-responsive">
                    <table class="nx-table">
                        <thead>
                            <tr>
                                <th>Incident</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Reported</th>
                                <th class="text-end">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incidents as $inc)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $inc->title }}</div>
                                        <div class="small text-muted text-truncate" style="max-width: 260px;">{{ $inc->description }}</div>
                                        @if($inc->resolution_note)
                                            <div class="small text-success mt-1">
                                                <strong>Resolution:</strong> {{ $inc->resolution_note }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status :status="$inc->severity" />
                                    </td>
                                    <td>
                                        <x-status :status="$inc->status" />
                                    </td>
                                    <td class="small text-muted">{{ $inc->created_at->format('M d, Y') }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editModal{{ $inc->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Resolution Modal -->
                                <div class="modal fade" id="editModal{{ $inc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('platform.incidents.update', $inc) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold">Update Incident #{{ $inc->id }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="open" {{ $inc->status === 'open' ? 'selected' : '' }}>Open</option>
                                                            <option value="investigating" {{ $inc->status === 'investigating' ? 'selected' : '' }}>Investigating</option>
                                                            <option value="resolved" {{ $inc->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Resolution Note (Required to resolve)</label>
                                                        <textarea name="resolution_note" class="form-control" rows="3" placeholder="Document investigation findings and remedial action taken.">{{ $inc->resolution_note }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No security incidents recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($incidents->hasPages())
                <div class="p-3 border-top">
                    {{ $incidents->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
