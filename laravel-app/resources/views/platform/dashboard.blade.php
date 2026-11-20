@extends('layouts.app')

@section('title', 'Platform Overview')
@section('page_title', 'Platform Administration')

@section('content')
<x-page-header title="Platform Administration" subtitle="Infrastructure health, organization tenants, security monitoring, and operations">
    <a href="{{ route('platform.health') }}" class="btn btn-outline-secondary">
        <i class="bi bi-activity me-1"></i>System Diagnostics
    </a>
    <a href="{{ route('organizations.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Organization
    </a>
</x-page-header>

<!-- Platform Boundary Callout Banner -->
<div class="alert alert-info border d-flex align-items-start gap-3 mb-4 shadow-sm" role="alert">
    <i class="bi bi-shield-lock-fill fs-4 text-primary flex-shrink-0 mt-1"></i>
    <div>
        <h6 class="fw-bold mb-1">Platform Plane Boundary Enforced</h6>
        <p class="small mb-0 text-muted">
            As a Platform Super Administrator, you manage system health, multi-tenant organizations, and security incident response.
            In compliance with strict data protection and two-plane architectural separation, <strong>platform administrators have zero access to organization document content, AI chats, and search queries</strong>.
        </p>
    </div>
</div>

<!-- Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <x-stat label="Active Orgs" :value="$activeOrgs . ' / ' . $totalOrgs" icon="bi-diagram-3" tone="primary" />
    </div>
    <div class="col-sm-6 col-xl-2">
        <x-stat label="Active Users" :value="$activeUsers" icon="bi-people" tone="success" />
    </div>
    <div class="col-sm-6 col-xl-2">
        <x-stat label="Platform Documents" :value="$totalDocs" icon="bi-files" tone="info" />
    </div>
    <div class="col-sm-6 col-xl-2">
        <x-stat label="Open Incidents" :value="$openIncidents" icon="bi-shield-exclamation" tone="warning" />
    </div>
    <div class="col-sm-6 col-xl-2">
        <x-stat label="Failed Logins (24h)" :value="$failedLogins24h" icon="bi-person-x" tone="danger" />
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="nx-stat-card">
            <div class="nx-stat-icon {{ ($aiHealth['status'] ?? '') === 'healthy' ? 'success' : 'danger' }}">
                <i class="bi bi-cpu"></i>
            </div>
            <div>
                <div class="nx-stat-value fs-5">{{ strtoupper($aiHealth['status'] ?? 'OFFLINE') }}</div>
                <div class="nx-stat-label">AI Engine</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Management Cards -->
    <div class="col-lg-6">
        <div class="nx-card h-100">
            <div class="nx-card-header">
                <span>Administrative Actions</span>
            </div>
            <div class="nx-card-body">
                <div class="list-group list-group-flush">
                    <a href="{{ route('organizations.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-building fs-4 text-primary"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold">Manage Organizations</h6>
                                <small class="text-muted">Register tenants, view usage, or suspend organization accounts</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <a href="{{ route('platform.recovery') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-key-fill fs-4 text-warning"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold">Account Recovery & Sessions</h6>
                                <small class="text-muted">Dispatch password reset links, revoke active sessions, toggle locks</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <a href="{{ route('platform.incidents') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-shield-shaded fs-4 text-danger"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold">Security Incidents Register</h6>
                                <small class="text-muted">Track anomalies, record mitigation, and review targeted accounts</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <a href="{{ route('audit.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-journal-text fs-4 text-info"></i>
                            <div>
                                <h6 class="mb-0 fw-semibold">Platform Audit Trail</h6>
                                <small class="text-muted">System-wide sanitized audit logs across all organizations</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Administrative Activity -->
    <div class="col-lg-6">
        <div class="nx-card h-100">
            <div class="nx-card-header">
                <span>Recent Platform Activity</span>
                <span class="small text-muted">Sanitized telemetry</span>
            </div>
            <div class="nx-card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentAdminActivity as $log)
                        <div class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-dark border font-monospace">{{ $log->action }}</span>
                                <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                            </div>
                            <div class="small text-muted mt-1">
                                User: {{ $log->user?->name ?? 'System/Guest' }} &bull; IP: {{ $log->ip_address }}
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted">No recent administrative events recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
