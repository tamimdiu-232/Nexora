@extends('layouts.app')
@section('title', 'Related documents')
@section('content')
<x-page-header title="Related documents" :subtitle="$document->title">
    <a class="btn btn-ghost" href="{{ route('documents.show', $document) }}"><i class="bi bi-arrow-left me-1"></i> Back to document</a>
</x-page-header>
<p class="text-muted small">Based on similar content and shared terms, limited to documents you can access. Similarity is a hint, not proof of a relationship.</p>
@if($error)<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ $error }}</div></div>@endif
@if($document->processing_status !== 'completed')<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i><div>This document has not been processed yet.</div></div>@endif
<div class="nx-card">
    @forelse($items as $item)
        <div class="nx-result">
            <a class="fw-semibold" href="{{ route('documents.show', $item['doc']) }}">{{ $item['doc']->title }}</a>
            <span class="nx-tag ms-1">{{ $item['doc']->category }}</span> <span class="nx-pill ms-1">similarity {{ $item['score'] }}</span>
            @if(!empty($item['explanation']))<div class="small text-muted mt-1">{{ $item['explanation'] }}</div>@endif
            <div class="nx-quote">{{ $item['snippet'] ?? '' }}</div>
        </div>
    @empty
        @if(!$error && $document->processing_status === 'completed')<x-empty icon="bi-diagram-2" title="No related documents">Nothing similar was found among your documents.</x-empty>@endif
    @endforelse
</div>
@endsection
