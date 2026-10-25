@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
<x-page-header title="Audit log" subtitle="Who did what, and when. Passwords, secrets and document text are never recorded." />
<form class="mb-3" method="GET"><div class="input-group"><input class="form-control" name="action" value="{{ request('action') }}" placeholder="Filter by action prefix, e.g. document. or review."><button class="btn btn-ghost">Filter</button></div></form>
<div class="nx-card">
    @if($logs->isEmpty())
        <x-empty icon="bi-journal-text" title="No entries">Activity will be listed here.</x-empty>
    @else
    <div class="table-responsive"><table class="nx-table">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Subject</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        @foreach($logs as $log)
            <tr><td class="text-nowrap text-muted">{{ $log->created_at?->format('d M Y H:i:s') }}</td><td>{{ $log->user?->name ?? '-' }}</td><td><code>{{ $log->action }}</code></td>
                <td class="text-muted">{{ $log->subject_type ? class_basename($log->subject_type) . ' #' . $log->subject_id : '-' }}</td>
                <td class="text-muted">{{ $log->ip_address }}</td><td class="small text-muted">{{ $log->metadata ? json_encode($log->metadata) : '' }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
