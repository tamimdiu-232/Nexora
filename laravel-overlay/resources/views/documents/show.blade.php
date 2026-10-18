@extends('layouts.app')
@section('title', 'Document')
@section('content')
@php $meta = $document->metadata; $unknown = '<span class="nx-unknown">Unknown</span>'; @endphp
<x-page-header :title="$document->title">
    <a class="btn btn-ghost btn-sm" href="{{ route('documents.download', $document) }}"><i class="bi bi-download me-1"></i> Download</a>
    <a class="btn btn-ghost btn-sm" href="{{ route('documents.related', $document) }}"><i class="bi bi-diagram-2 me-1"></i> Related</a>
    @if($document->processing_status === 'completed')
        <form method="POST" action="{{ route('documents.summarize', $document) }}" data-loading="Summarizing...">@csrf
            <button class="btn btn-soft btn-sm" type="submit"><i class="bi bi-magic me-1"></i> {{ $meta?->summary ? 'Refresh summary' : 'Summarize' }}</button></form>
    @endif
    @can('reprocess', $document)
        <form method="POST" action="{{ route('documents.reprocess', $document) }}" data-loading="Queuing...">@csrf
            <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-arrow-repeat me-1"></i> {{ $document->processing_status === 'failed' ? 'Retry processing' : 'Reprocess' }}</button></form>
    @endcan
    @if($canModify)
        <a class="btn btn-ghost btn-sm" href="{{ route('documents.edit', $document) }}"><i class="bi bi-pencil me-1"></i> Edit</a>
        <form method="POST" action="{{ route('documents.archive', $document) }}">@csrf
            <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-archive me-1"></i> {{ $document->archived_at ? 'Restore' : 'Archive' }}</button></form>
    @endif
    @can('delete', $document)
        <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="Delete this document? It will disappear from search and AI answers.">@csrf @method('DELETE')
            <button class="btn btn-ghost btn-sm text-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete</button></form>
    @endcan
</x-page-header>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <x-status :status="$document->processing_status" />
    <span class="nx-tag">{{ $document->category }}</span>
    @if($document->category_source === 'ai' && $document->category_confidence !== null)<span class="small text-muted">AI score {{ number_format($document->category_confidence * 100) }}% (heuristic)</span>
    @elseif($document->category_source === 'manual')<span class="small text-muted">category set by a person</span>@endif
    @if($document->archived_at)<span class="nx-pill">archived</span>@endif
</div>

@if($document->processing_status === 'failed')
    <div class="alert alert-danger"><i class="bi bi-x-octagon-fill"></i><div>Processing failed: {{ $document->processing_error }}@if($lastJob?->error_code) <code>({{ $lastJob->error_code }})</code>@endif</div></div>
@elseif(in_array($document->processing_status, ['pending', 'processing']))
    <div class="alert alert-info"><i class="bi bi-hourglass-split"></i><div>This document is {{ $document->processing_status }}. Refresh in a moment. If nothing happens, run <code>php artisan govdoc:process</code>.</div></div>
@endif

<div class="row g-3">
    <div class="col-xl-5">
        <div class="nx-card mb-3">
            <div class="nx-card-head"><h2>Details</h2></div>
            <dl class="nx-kv">
                <div><dt>Organization</dt><dd>{{ $document->organization->name }}</dd></div>
                <div><dt>Department</dt><dd>{{ $document->department?->name ?? 'Organization-wide' }}</dd></div>
                <div><dt>Uploaded by</dt><dd>{{ $document->uploader?->name ?? '-' }} · {{ $document->created_at->format('d M Y, H:i') }}</dd></div>
                <div><dt>Pages</dt><dd>{{ $document->page_count ?? '-' }}</dd></div>
                <div><dt>Tags</dt><dd>{{ $document->tags ?: '-' }}</dd></div>
                <div><dt>Description</dt><dd>{{ $document->description ?: '-' }}</dd></div>
            </dl>
        </div>
        <div class="nx-card mb-3">
            <div class="nx-card-head"><h2>Extracted information</h2><span class="nx-pill">unverified</span></div>
            <dl class="nx-kv">
                <div><dt>Issuing organization</dt><dd>{!! $meta?->issuing_organization ? e($meta->issuing_organization) : $unknown !!}</dd></div>
                <div><dt>Reference no.</dt><dd>{!! $meta?->reference_number ? e($meta->reference_number) : $unknown !!}</dd></div>
                <div><dt>Document date</dt><dd>{!! $meta?->document_date ? e($meta->document_date) : $unknown !!}</dd></div>
                <div><dt>Project</dt><dd>{!! $meta?->project_name ? e($meta->project_name) : $unknown !!}</dd></div>
                <div><dt>Budget</dt><dd>{!! $meta?->budget ? e($meta->budget) : $unknown !!}</dd></div>
                <div><dt>Deadline</dt><dd>{!! $meta?->deadline ? e($meta->deadline) : $unknown !!}</dd></div>
                <div><dt>Keywords</dt><dd>{{ $meta?->keywords ? implode(', ', $meta->keywords) : '-' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="col-xl-7">
        @if($meta?->summary)
            @php $summary = $meta->summary; @endphp
            <div class="nx-card mb-3">
                <div class="nx-card-head"><h2>Summary</h2><span class="nx-pill nx-pill-medium">AI-generated, not verified</span></div>
                <div class="nx-card-body small">
                    <h6 class="text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.06em">Short summary</h6>
                    @foreach($summary['short'] ?? [] as $item)
                        <p class="mb-1">{{ $item['text'] }} <a href="{{ route('documents.show', [$document, 'page' => $item['page']]) }}#preview">[p.{{ $item['page'] }}]</a></p>
                    @endforeach
                    <h6 class="text-uppercase text-muted mt-3" style="font-size:.72rem;letter-spacing:.06em">Key points</h6>
                    <ul class="ps-3">@foreach($summary['key_points'] ?? [] as $item)<li>{{ $item['text'] }} <a href="{{ route('documents.show', [$document, 'page' => $item['page']]) }}#preview">[p.{{ $item['page'] }}]</a></li>@endforeach</ul>
                    <details class="mb-2"><summary class="fw-medium">Detailed summary</summary>
                        @foreach($summary['detailed'] ?? [] as $item)<p class="mb-1 mt-1">{{ $item['text'] }} <span class="text-muted">[p.{{ $item['page'] }}]</span></p>@endforeach</details>
                    @if(!empty($summary['action_items']))
                        <h6 class="text-uppercase text-muted mt-3" style="font-size:.72rem;letter-spacing:.06em">Action items stated in the document</h6>
                        <ul class="ps-3">@foreach($summary['action_items'] as $item)<li>{{ $item['text'] }} <span class="text-muted">[p.{{ $item['page'] }}]</span></li>@endforeach</ul>
                    @endif
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach(array_merge($summary['amounts'] ?? [], $summary['dates'] ?? []) as $fact)<span class="nx-tag">{{ $fact['value'] }} · p.{{ $fact['page'] }}</span>@endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="nx-card" id="preview">
            <div class="nx-card-head"><h2>Preview</h2>@if(request('page'))<span class="small text-muted">Page {{ (int) request('page') }}</span>@endif</div>
            <iframe class="nx-pdf" title="PDF preview" src="{{ route('documents.preview', $document) }}#page={{ max(1, (int) request('page', 1)) }}"></iframe>
        </div>
    </div>
</div>
@endsection
