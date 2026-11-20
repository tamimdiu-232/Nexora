@extends('layouts.app')

@section('title', 'System Health')
@section('page_title', 'System Health & Diagnostics')

@section('content')
<x-page-header title="System Health & Diagnostics" subtitle="Runtime status, OCR models, processing telemetry, and sanitized error logs" />

@if(config('app.debug') && config('app.env') === 'production')
    <div class="alert alert-danger border d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><strong>Security Warning:</strong> <code>APP_DEBUG</code> is enabled in a production environment. Please disable debug mode in your <code>.env</code> file.</div>
    </div>
@endif

<div class="row g-3 mb-4">
    <!-- Component Cards -->
    <div class="col-md-3">
        <div class="nx-card p-3">
            <div class="text-muted small fw-semibold">Database Connection</div>
            <div class="fs-5 fw-bold mt-1 {{ $dbOk ? 'text-success' : 'text-danger' }}">
                <i class="bi {{ $dbOk ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                {{ $dbOk ? 'Connected' : 'Disconnected' }}
            </div>
            <div class="small text-muted mt-1">Driver: {{ config('database.default') }}</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="nx-card p-3">
            <div class="text-muted small fw-semibold">Private Storage Disk</div>
            <div class="fs-5 fw-bold mt-1 {{ $storageWritable ? 'text-success' : 'text-danger' }}">
                <i class="bi {{ $storageWritable ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                {{ $storageWritable ? 'Writable' : 'Read-Only / Error' }}
            </div>
            <div class="small text-muted mt-1">Path: storage/app/private</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="nx-card p-3">
            <div class="text-muted small fw-semibold">AI Microservice</div>
            <div class="fs-5 fw-bold mt-1 {{ ($aiStatus['status'] ?? '') === 'healthy' ? 'text-success' : 'text-danger' }}">
                <i class="bi {{ ($aiStatus['status'] ?? '') === 'healthy' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                {{ ucfirst($aiStatus['status'] ?? 'Unavailable') }}
            </div>
            <div class="small text-muted mt-1">Port: 8001 &bull; Token Verified</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="nx-card p-3">
            <div class="text-muted small fw-semibold">Queue Pipeline</div>
            <div class="fs-5 fw-bold mt-1 text-primary">
                <i class="bi bi-layers-fill me-1"></i>
                {{ ucfirst(config('queue.default', 'sync')) }}
            </div>
            <div class="small text-muted mt-1">PHP: {{ PHP_VERSION }} &bull; Laravel: {{ app()->version() }}</div>
        </div>
    </div>
</div>

<!-- OCR & AI Model Capabilities -->
<div class="nx-card mb-4">
    <div class="nx-card-header">
        <span>OCR & Intelligence Model Diagnostics</span>
    </div>
    <div class="nx-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="small fw-bold text-secondary mb-2">Tesseract OCR Configuration</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th class="bg-light w-50">Tesseract Engine</th>
                        <td>{{ ($aiStatus['ocr']['tesseract_available'] ?? false) ? 'Installed' : 'Missing' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Poppler (pdftoppm)</th>
                        <td>{{ ($aiStatus['ocr']['pdftoppm_available'] ?? false) ? 'Installed (300 DPI Rendering)' : 'Missing' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Active OCR Languages</th>
                        <td>{{ $aiStatus['ocr']['active_langs'] ?? 'eng' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Bangla Language Model (ben)</th>
                        <td>
                            @if($aiStatus['ocr']['bangla_ready'] ?? false)
                                <span class="badge bg-success">Installed & Verified</span>
                            @else
                                <span class="badge bg-warning text-dark">Missing (Run: brew install tesseract-lang)</span>
                            @endif
                        </td>
                    </tr>
                </table>
                @if(!empty($aiStatus['ocr']['warning']))
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ $aiStatus['ocr']['warning'] }}
                    </div>
                @endif
            </div>

            <div class="col-md-6">
                <h6 class="small fw-bold text-secondary mb-2">Vector Store & Retrieval Engine</h6>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th class="bg-light w-50">Vector Store State</th>
                        <td>{{ ($aiStatus['vector_store']['healthy'] ?? false) ? 'Healthy' : 'Needs Reindex' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Indexed Documents</th>
                        <td>{{ $aiStatus['vector_store']['indexed_documents'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Total Indexed Chunks</th>
                        <td>{{ $aiStatus['vector_store']['total_chunks'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Answer Engine</th>
                        <td>{{ $aiStatus['llm']['provider'] ?? 'Extractive' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Processing Telemetry -->
    <div class="col-lg-5">
        <div class="nx-card h-100">
            <div class="nx-card-header">
                <span>Recent Processing Failures (Sanitized)</span>
            </div>
            <div class="nx-card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentFailures as $failure)
                        <div class="list-group-item py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="small">{{ $failure['organization'] }}</strong>
                                <span class="badge bg-danger-subtle text-danger border font-monospace">{{ $failure['error_code'] }}</span>
                            </div>
                            <div class="small text-muted mt-1">{{ $failure['error_message'] }}</div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted small">No processing failures recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Sanitized Error Log Viewer -->
    <div class="col-lg-7">
        <div class="nx-card h-100">
            <div class="nx-card-header d-flex justify-content-between align-items-center">
                <span>System Error Log (Sanitized & Masked)</span>
                <span class="badge bg-light text-muted">Latest {{ count($errorLogs) }} entries</span>
            </div>
            <div class="nx-card-body p-0" style="max-height: 380px; overflow-y: auto;">
                @if(empty($errorLogs))
                    <div class="p-4 text-center text-muted small">Log clean: No ERROR-level entries in the scanned window.</div>
                @else
                    <div class="list-group list-group-flush font-monospace small" style="font-size: 0.78rem;">
                        @foreach($errorLogs as $entry)
                            <div class="list-group-item p-2">
                                <div class="text-danger fw-semibold">
                                    [{{ $entry['timestamp'] }}] {{ $entry['level'] }}
                                </div>
                                <div class="text-secondary text-break mt-1">{{ $entry['message'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
