@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
<h3>Audit log</h3>
<form class="mb-3" method="GET"><div class="input-group"><input class="form-control" name="action" value="{{ request('action') }}" placeholder="Action prefix, e.g. document. or review."><button class="btn btn-outline-secondary">Filter</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Subject</th><th>IP</th><th>Details</th></tr></thead><tbody>
@forelse($logs as $l)<tr><td class="text-nowrap">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td><td>{{ $l->user?->name ?? '-' }}</td><td><code>{{ $l->action }}</code></td>
<td>{{ $l->subject_type ? class_basename($l->subject_type) . ' #' . $l->subject_id : '-' }}</td><td>{{ $l->ip_address }}</td><td class="small">{{ $l->metadata ? json_encode($l->metadata) : '' }}</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-3">No entries.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
