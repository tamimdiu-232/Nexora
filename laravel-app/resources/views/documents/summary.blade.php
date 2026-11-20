@extends('layouts.app')

@section('title', 'Summary — ' . $document->title)
@section('page_title', 'Document Executive Summary')

@section('content')
<x-page-header title="Executive Summary" subtitle="AI-generated hierarchical extractive summary for: {{ $document->title }}">
    <a href="{{ route('documents.show', $document) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Document
    </a>
</x-page-header>

<!-- Notice Banner -->
<div class="alert alert-warning border d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
    <div class="small">
        <strong>Notice:</strong> This summary is <em>AI-generated and not verified by a human reviewer</em>. It is extractively constructed from high-frequency institutional sentence clusters and explicit obligation patterns.
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Short Summary Card -->
        <div class="nx-card mb-4">
            <div class="nx-card-header bg-light">
                <span class="fw-bold">Short Summary</span>
            </div>
            <div class="nx-card-body">
                <p class="fs-6 lead text-dark mb-0" style="line-height: 1.7;">
                    {{ $summary['short'] ?? 'No short summary available.' }}
                </p>
            </div>
        </div>

        <!-- Detailed Summary Card -->
        <div class="nx-card mb-4">
            <div class="nx-card-header bg-light">
                <span class="fw-bold">Detailed Summary</span>
            </div>
            <div class="nx-card-body">
                <p class="text-secondary mb-0" style="line-height: 1.8;">
                    {{ $summary['detailed'] ?? 'No detailed summary available.' }}
                </p>
            </div>
        </div>

        <!-- Key Points -->
        <div class="nx-card mb-4">
            <div class="nx-card-header bg-light">
                <span class="fw-bold">Key Document Points</span>
            </div>
            <div class="nx-card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($summary['key_points'] ?? [] as $kp)
                        <li class="list-group-item d-flex align-items-start gap-3 py-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill flex-shrink-0 mt-1">
                                Page {{ $kp['page'] ?? '?' }}
                            </span>
                            <div class="text-dark">{{ $kp['text'] ?? '' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted p-3">No key points extracted.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Action Items (Explicit Obligations) -->
        @if(!empty($summary['action_items']))
            <div class="nx-card mb-4 border-warning">
                <div class="nx-card-header bg-warning-subtle text-warning-emphasis">
                    <span class="fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Mandatory Action Items & Obligations</span>
                </div>
                <div class="nx-card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($summary['action_items'] as $item)
                            <li class="list-group-item d-flex align-items-start gap-3 py-3">
                                <span class="badge bg-warning text-dark flex-shrink-0 mt-1">
                                    Page {{ $item['page'] ?? '?' }}
                                </span>
                                <div class="fw-medium text-dark">{{ $item['text'] ?? '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <!-- Metadata Sidebar -->
    <div class="col-lg-4">
        <div class="nx-card mb-4">
            <div class="nx-card-header">
                <span>Identified Amounts</span>
            </div>
            <div class="nx-card-body p-0">
                <ul class="list-group list-group-flush small">
                    @forelse($summary['amounts'] ?? [] as $amt)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="fw-bold text-success">{{ $amt['value'] }}</span>
                            <span class="text-muted">Page {{ $amt['page'] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted p-3">No currency amounts located.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="nx-card">
            <div class="nx-card-header">
                <span>Key Dates & Milestones</span>
            </div>
            <div class="nx-card-body p-0">
                <ul class="list-group list-group-flush small">
                    @forelse($summary['dates'] ?? [] as $dt)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="fw-semibold text-primary">{{ $dt['value'] }}</span>
                            <span class="text-muted">Page {{ $dt['page'] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted p-3">No dates identified.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
