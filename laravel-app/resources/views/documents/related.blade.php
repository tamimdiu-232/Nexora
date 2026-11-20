@extends('layouts.app')

@section('title', 'Related Documents — ' . $document->title)
@section('page_title', 'Related Institutional Documents')

@section('content')
<x-page-header title="Related Documents" subtitle="Vector centroid similarity & topic overlap with: {{ $document->title }}">
    <a href="{{ route('documents.show', $document) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Document
    </a>
</x-page-header>

<div class="row">
    <div class="col-lg-9">
        @forelse($relatedList as $rel)
            @php
                $target = $rel['document'];
            @endphp
            <div class="nx-card mb-3 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <a href="{{ route('documents.show', $target) }}" class="h6 fw-bold text-dark text-decoration-none hover-text-primary mb-1 d-block">
                            {{ $target->title }}
                        </a>
                        <div class="small text-muted">
                            {{ $target->department?->name ?? 'Organization-wide' }} &bull;
                            Category: {{ $target->category }}
                        </div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-2 px-3 fw-medium">
                        Similarity: {{ round($rel['similarity'] * 100) }}%
                    </span>
                </div>

                <div class="small text-secondary bg-light p-2 rounded-2 mb-2">
                    <strong>Relation basis:</strong> {{ $rel['explanation'] }}
                </div>

                @if(!empty($rel['shared_keywords']))
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        <small class="text-muted me-1">Shared topics:</small>
                        @foreach($rel['shared_keywords'] as $skw)
                            <span class="badge bg-light text-dark border">{{ $skw }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="nx-card p-5 text-center">
                <x-empty icon="bi-diagram-3" title="No related documents found" subtitle="No other accessible documents share strong topical or vector similarity." />
            </div>
        @endforelse
    </div>
</div>
@endsection
