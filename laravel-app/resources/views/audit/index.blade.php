@extends('layouts.app')

@section('title', 'Audit Log')
@section('page_title', 'Institutional Audit Trail')

@section('content')
<x-page-header title="Institutional Audit Trail" subtitle="Cryptographically sanitized, immutable log of administrative and intelligence actions" />

<div class="nx-card mb-4">
    <div class="nx-card-header">
        <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-center w-100">
            <div class="col-sm-6 col-md-3">
                <input type="text" name="action" class="form-control form-control-sm" placeholder="Filter by action prefix (e.g. document., auth., user.)..." value="{{ request('action') }}">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                @if(request()->has('action'))
                    <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>

            <div class="col-auto ms-auto text-muted small">
                <i class="bi bi-shield-check text-success me-1"></i>All secrets and document texts strictly masked
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Actor</th>
                    <th>Target / Subject</th>
                    <th>IP Address</th>
                    <th>Sanitized Metadata</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-muted font-monospace" style="font-size: 0.78rem;">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="small">
                            @if($log->user)
                                <div class="fw-semibold text-dark">{{ $log->user->name }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $log->user->email }}</div>
                            @else
                                <span class="text-muted fst-italic">System / Guest</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            @if($log->subject_type)
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.7rem;">
                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                </span>
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="small font-monospace text-muted">{{ $log->ip_address }}</td>
                        <td class="small font-monospace text-muted" style="max-width: 280px; font-size: 0.75rem;">
                            @if(!empty($log->metadata))
                                <div class="text-truncate" title="{{ json_encode($log->metadata) }}">
                                    {{ json_encode($log->metadata) }}
                                </div>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No audit events found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="p-3 border-top">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
