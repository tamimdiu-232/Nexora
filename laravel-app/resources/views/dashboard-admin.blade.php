@extends('layouts.app')
@section('title', 'Platform overview')
@section('content')
<x-page-header title="Platform overview" subtitle="Organizations, platform health and security. Organization content is never shown here." />

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat label="Organizations" :value="$organizations['active'] . ' / ' . $organizations['total']" icon="bi-buildings" tone="primary" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Active users" :value="$activeUsers" icon="bi-people" tone="info" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Open incidents" :value="$openIncidents" icon="bi-shield-exclamation" :tone="$openIncidents ? 'danger' : 'success'" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Failed sign-ins (24h)" :value="$failedLogins24h" icon="bi-person-x" :tone="$failedLogins24h > 10 ? 'warning' : 'success'" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Platform health</h2><a class="small" href="{{ route('admin.system') }}">Details</a></div>
            <ul class="nx-list">
                <li><span>AI service</span><x-status :status="($aiHealth['status'] ?? '') === 'ok' ? 'ok' : 'unreachable'" /></li>
                <li><span>Documents stored (all organizations)</span><b>{{ $documentCount }}</b></li>
                <li><span>Processing failures (24h)</span><b class="{{ $processingFailures24h ? 'text-danger' : '' }}">{{ $processingFailures24h }}</b></li>
            </ul>
            <div class="nx-card-body border-top small text-muted">
                <i class="bi bi-lock-fill me-1"></i> Platform administrators cannot open organization documents, search results or AI conversations.
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Recent administrative activity</h2><a class="small" href="{{ route('audit.index') }}">Audit log</a></div>
            <ul class="nx-list small">
                @forelse($activity as $entry)
                    <li><span><code>{{ $entry->action }}</code> <span class="text-muted">by {{ $entry->user?->name ?? 'system' }}</span></span>
                        <span class="text-muted text-nowrap">{{ $entry->created_at?->diffForHumans() }}</span></li>
                @empty
                    <li class="text-muted">Nothing yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
