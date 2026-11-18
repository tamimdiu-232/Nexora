@extends('layouts.app')

@section('title', 'Workspace Dashboard')
@section('page_title', 'Institutional Workspace')

@section('content')
<x-page-header title="Workspace Overview" subtitle="Document repository metrics, intelligence pipeline, and institutional activity">
    @if($user->hasPermission('documents.upload'))
        <a href="{{ route('documents.create') }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-cloud-arrow-up me-2"></i>Upload Document
        </a>
    @endif
    @if($user->hasPermission('chat.use'))
        <a href="{{ route('chat.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-stars me-2"></i>Ask AI
        </a>
    @endif
</x-page-header>

<!-- Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <x-stat label="Accessible Documents" :value="$totalAccessible" icon="bi-files" tone="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-stat label="Processed & Indexed" :value="$processedCount" icon="bi-check2-all" tone="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-stat label="In Pipeline / Queue" :value="$processingCount" icon="bi-arrow-repeat" tone="info" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-stat label="Processing Issues" :value="$failedCount" icon="bi-exclamation-triangle" tone="danger" />
    </div>
</div>

@if($pendingReviewsCount > 0)
    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4 border" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-patch-question-fill fs-5 text-warning"></i>
            <div>
                <strong>{{ $pendingReviewsCount }} AI answer(s) pending human reviewer verification</strong> in your organization.
            </div>
        </div>
        <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-warning fw-medium">View Reviews</a>
    </div>
@endif

<div class="row g-4 mb-4">
    <!-- Category Distribution -->
    <div class="col-lg-5">
        <div class="nx-card h-100">
            <div class="nx-card-header">
                <span>Documents by Category</span>
                <span class="badge bg-light text-muted">{{ count($categories) }} Categories</span>
            </div>
            <div class="nx-card-body">
                @if(empty($categories))
                    <x-empty icon="bi-pie-chart" title="No categorized documents yet" subtitle="Upload documents to populate intelligence metrics." />
                @else
                    <div class="d-flex flex-column gap-3">
                        @foreach($categories as $catName => $count)
                            @php
                                $pct = $totalAccessible > 0 ? round(($count / $totalAccessible) * 100) : 0;
                            @endphp
                            <div>
                                <div class="d-flex justify-content-between small fw-medium mb-1">
                                    <span>{{ $catName }}</span>
                                    <span class="text-muted">{{ $count }} ({{ $pct }}%)</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- AI Intelligence Status -->
    <div class="col-lg-7">
        <div class="nx-card h-100">
            <div class="nx-card-header">
                <span>AI Retrieval & OCR Engine</span>
                @if($aiHealth && ($aiHealth['status'] ?? '') === 'healthy')
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Service Online
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Service Degraded
                    </span>
                @endif
            </div>
            <div class="nx-card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small fw-semibold">OCR Pipeline</div>
                            <div class="fs-6 fw-bold mt-1 text-dark">
                                @if($aiHealth && ($aiHealth['ocr']['tesseract_available'] ?? false))
                                    Tesseract 300 DPI Active
                                @else
                                    <span class="text-danger">OCR Unavailable</span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">
                                Languages: {{ implode(', ', $aiHealth['ocr']['languages'] ?? ['eng']) }}
                                @if($aiHealth && ($aiHealth['ocr']['bangla_ready'] ?? false))
                                    <span class="badge bg-success-subtle text-success ms-1">Bangla Ready</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning ms-1">Bangla Missing</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small fw-semibold">Answer Engine</div>
                            <div class="fs-6 fw-bold mt-1 text-dark">
                                Grounded Extractive (Default)
                            </div>
                            <div class="small text-muted mt-1">
                                Strict source citations, conflict detection enabled.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 border rounded-3 bg-white">
                    <h6 class="small fw-bold mb-2">Bilingual Retrieval Capabilities</h6>
                    <p class="small text-muted mb-0">
                        Ask natural questions in Bengali, English, or mixed:
                        <code class="text-primary fw-medium">"এই project-এর budget কত এবং deadline কবে?"</code>.
                        The system cites verified source pages and alerts reviewers of conflicting revisions.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Documents Table -->
<div class="nx-card mb-4">
    <div class="nx-card-header">
        <span>Recent Documents</span>
        <a href="{{ route('documents.index') }}" class="small text-primary text-decoration-none">View All Documents</a>
    </div>
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>Title & Reference</th>
                    <th>Category</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Uploaded</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentDocuments as $doc)
                    <tr>
                        <td>
                            <a href="{{ route('documents.show', $doc) }}" class="fw-semibold text-dark text-decoration-none hover-text-primary">
                                {{ $doc->title }}
                            </a>
                            <div class="small text-muted">{{ $doc->original_name }} &bull; {{ number_format($doc->size_bytes / 1024, 1) }} KB</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">{{ $doc->category }}</span>
                        </td>
                        <td>{{ $doc->department?->name ?? 'Organization-wide' }}</td>
                        <td>
                            <x-status :status="$doc->processing_status" />
                        </td>
                        <td class="text-muted small">{{ $doc->created_at->diffForHumans() }}</td>
                        <td class="text-end">
                            <a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-light border" title="View details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            No documents uploaded yet. Click "Upload Document" to begin.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
