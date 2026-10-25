@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-page-header title="Dashboard" subtitle="Your documents and activity at a glance.">
    @if(auth()->user()->hasPermission('documents.upload'))<a class="btn btn-primary" href="{{ route('documents.create') }}"><i class="bi bi-cloud-arrow-up me-1"></i> Upload</a>@endif
    @if(auth()->user()->hasPermission('chat.use'))<a class="btn btn-soft" href="{{ route('chat.index') }}"><i class="bi bi-stars me-1"></i> Ask AI</a>@endif
</x-page-header>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat label="Accessible documents" :value="$total" icon="bi-files" tone="primary" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Processed" :value="$byStatus['completed'] ?? 0" icon="bi-check2-circle" tone="success" /></div>
    <div class="col-6 col-xl-3"><x-stat label="In queue" :value="($byStatus['pending'] ?? 0) + ($byStatus['processing'] ?? 0)" icon="bi-hourglass-split" tone="info" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Failed" :value="$byStatus['failed'] ?? 0" icon="bi-exclamation-octagon" tone="danger" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Documents by category</h2></div>
            <div class="nx-card-body">
                @forelse($byCategory as $category => $count)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small"><span class="fw-medium">{{ $category }}</span><span class="text-muted">{{ $count }}</span></div>
                        <div class="nx-meter"><span style="width: {{ $total ? round($count / $total * 100) : 0 }}%"></span></div>
                    </div>
                @empty
                    <x-empty icon="bi-bar-chart" title="No documents yet">Upload a PDF to see your categories here.</x-empty>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Needs attention</h2></div>
            <ul class="nx-list">
                @forelse($failed as $document)
                    <li><div><a class="fw-medium" href="{{ route('documents.show', $document) }}">{{ $document->title }}</a>
                        <div class="small text-danger">{{ $document->processing_error }}</div></div><x-status status="failed" /></li>
                @empty
                    <li class="text-muted"><span><i class="bi bi-check-circle text-success me-1"></i> No failed documents.</span></li>
                @endforelse
            </ul>
            @if(auth()->user()->hasPermission('reviews.view'))
                <div class="nx-card-body border-top small">
                    <div class="text-muted mb-2">Review queue</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(['pending', 'verified', 'rejected', 'needs_correction'] as $status)
                            <span><x-status :status="$status" /> <b>{{ $reviewCounts[$status] ?? 0 }}</b></span>
                        @endforeach
                    </div>
                </div>
            @endif
            @if($aiHealth)
                <div class="nx-card-body border-top small d-flex justify-content-between align-items-center">
                    <span class="text-muted">AI service</span>
                    <span class="d-flex gap-2 align-items-center">@if(isset($aiHealth['documents_indexed']))<span class="text-muted">{{ $aiHealth['documents_indexed'] }} indexed</span>@endif
                        <x-status :status="($aiHealth['status'] ?? '') === 'ok' ? 'ok' : 'unreachable'" /></span>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="nx-card h-100">
            <div class="nx-card-head"><h2>Recent activity</h2></div>
            <ul class="nx-list small">
                @forelse($activity as $entry)
                    <li><span><code>{{ $entry->action }}</code><br><span class="text-muted">{{ $entry->user?->name ?? 'anonymous' }}</span></span>
                        <span class="text-muted text-nowrap">{{ $entry->created_at?->diffForHumans() }}</span></li>
                @empty
                    <li class="text-muted">No activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
