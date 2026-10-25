@extends('layouts.app')
@section('title', 'Security incidents')
@section('content')
<x-page-header title="Security incidents" subtitle="Record, track and close security issues. To suspend an organization, edit it under Organizations." />

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Report an incident</h2></div>
            <form method="POST" action="{{ route('admin.incidents.store') }}" class="nx-card-body" data-loading="Saving...">@csrf
                <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="255" value="{{ old('title') }}"></div>
                <div class="row g-2 mb-3">
                    <div class="col-5"><label class="form-label">Severity</label><select name="severity" class="form-select">@foreach(\App\Models\SecurityIncident::SEVERITIES as $s)<option value="{{ $s }}" @selected($s === 'medium')>{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div class="col-7"><label class="form-label">Organization</label><select name="organization_id" class="form-select"><option value="">Platform-wide</option>@foreach($organizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
                </div>
                <div class="mb-3"><label class="form-label">What happened?</label><textarea name="description" rows="3" class="form-control" maxlength="5000"></textarea>
                    <div class="form-text">Do not paste passwords or document content.</div></div>
                <button class="btn btn-primary w-100" type="submit">Record incident</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Incident register</h2></div>
            @forelse($incidents as $incident)
                <div class="nx-result">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <b>{{ $incident->title }}</b><x-status :status="$incident->severity" /><x-status :status="$incident->status" />
                        <span class="small text-muted ms-auto">{{ $incident->created_at->format('d M Y H:i') }} · {{ $incident->organization?->name ?? 'Platform-wide' }}</span>
                    </div>
                    @if($incident->description)<p class="small mt-2 mb-1">{{ $incident->description }}</p>@endif
                    @if($incident->resolution_note)<p class="small text-muted mb-1"><b>Resolution:</b> {{ $incident->resolution_note }}</p>@endif
                    @if($incident->status !== 'resolved')
                        <form method="POST" action="{{ route('admin.incidents.update', $incident) }}" class="row g-2 mt-1">@csrf @method('PUT')
                            <div class="col-md-3"><select name="status" class="form-select form-select-sm">@foreach(['open', 'investigating', 'resolved'] as $s)<option value="{{ $s }}" @selected($incident->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                            <div class="col-md-7"><input name="resolution_note" class="form-control form-control-sm" placeholder="Resolution note (required when resolved)"></div>
                            <div class="col-md-2"><button class="btn btn-soft btn-sm w-100" type="submit">Update</button></div>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty icon="bi-shield-check" title="No incidents">Nothing has been reported.</x-empty>
            @endforelse
        </div>
        <div class="mt-3">{{ $incidents->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<div class="nx-card">
    <div class="nx-card-head"><h2>Security events</h2>
        <span class="small text-muted">{{ $failedLoginCount }} failed sign-ins in the last 24 hours@if($topTargets->isNotEmpty()) · most targeted: {{ $topTargets->map(fn ($n, $email) => $email . ' (' . $n . ')')->implode(', ') }}@endif</span></div>
    <ul class="nx-list small">
        @forelse($events as $event)
            <li><span><code>{{ $event->action }}</code> <span class="text-muted">{{ $event->user?->name ?? 'anonymous' }}</span></span><span class="text-muted">{{ $event->created_at?->diffForHumans() }}</span></li>
        @empty<li class="text-muted">No security events yet.</li>@endforelse
    </ul>
</div>
@endsection
