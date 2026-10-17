@extends('layouts.app')
@section('title', $document->title)
@section('content')
@php $m = $document->metadata; $unknown = '<span class="text-muted">Unknown</span>'; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <h3 class="mb-1">{{ $document->title }}</h3>
        @include('partials.status', ['status' => $document->processing_status])
        <span class="badge bg-light text-dark border">{{ $document->category }}
            @if($document->category_source === 'ai' && $document->category_confidence !== null) - AI {{ number_format($document->category_confidence * 100) }}% (heuristic) @elseif($document->category_source === 'manual') - set by a person @endif</span>
        @if($document->archived_at)<span class="badge bg-dark">Archived</span>@endif
    </div>
    <div class="d-flex flex-wrap gap-1">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('documents.download', $document) }}">Download</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('documents.related', $document) }}">Related documents</a>
        @if($document->processing_status === 'completed')
        <form method="POST" action="{{ route('documents.summarize', $document) }}" data-loading="Summarizing...">@csrf<button class="btn btn-outline-primary btn-sm" type="submit">{{ $m?->summary ? 'Refresh summary' : 'Generate summary' }}</button></form>@endif
        @can('reprocess', $document)<form method="POST" action="{{ route('documents.reprocess', $document) }}" data-loading="Queuing...">@csrf<button class="btn btn-outline-warning btn-sm" type="submit">{{ $document->processing_status === 'failed' ? 'Retry processing' : 'Reprocess' }}</button></form>@endcan
        @if($canModify)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('documents.edit', $document) }}">Edit</a>
            <form method="POST" action="{{ route('documents.archive', $document) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">{{ $document->archived_at ? 'Restore' : 'Archive' }}</button></form>
        @endif
        @can('delete', $document)<form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="Delete this document? It will be removed from search and AI answers.">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Delete</button></form>@endcan
    </div>
</div>

@if($document->processing_status === 'failed')
<div class="alert alert-danger">Processing failed: {{ $document->processing_error }}@if($lastJob?->error_code) <code>({{ $lastJob->error_code }})</code>@endif</div>
@elseif(in_array($document->processing_status, ['pending', 'processing']))
<div class="alert alert-info">This document is {{ $document->processing_status }}. Refresh this page in a moment. (If the queue worker is not running, run <code>php artisan govdoc:process</code>.)</div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Details</div>
            <table class="table table-sm mb-0"><tbody>
                <tr><th>Organization</th><td>{{ $document->organization->name }}</td></tr>
                <tr><th>Department</th><td>{{ $document->department?->name ?? 'Organization-wide' }}</td></tr>
                <tr><th>Uploaded by</th><td>{{ $document->uploader?->name ?? '-' }}, {{ $document->created_at->format('Y-m-d H:i') }}</td></tr>
                <tr><th>Pages</th><td>{{ $document->page_count ?? '-' }}</td></tr>
                <tr><th>Tags</th><td>{{ $document->tags ?: '-' }}</td></tr>
                <tr><th>Description</th><td>{{ $document->description ?: '-' }}</td></tr>
            </tbody></table></div>
        <div class="card mb-3"><div class="card-header">Extracted information <span class="badge bg-secondary">AI / rules, unverified</span></div>
            <table class="table table-sm mb-0"><tbody>
                <tr><th>Issuing organization</th><td>{!! $m?->issuing_organization ? e($m->issuing_organization) : $unknown !!}</td></tr>
                <tr><th>Reference no.</th><td>{!! $m?->reference_number ? e($m->reference_number) : $unknown !!}</td></tr>
                <tr><th>Document date</th><td>{!! $m?->document_date ? e($m->document_date) : $unknown !!}</td></tr>
                <tr><th>Project</th><td>{!! $m?->project_name ? e($m->project_name) : $unknown !!}</td></tr>
                <tr><th>Budget</th><td>{!! $m?->budget ? e($m->budget) : $unknown !!}</td></tr>
                <tr><th>Deadline</th><td>{!! $m?->deadline ? e($m->deadline) : $unknown !!}</td></tr>
                <tr><th>Keywords</th><td>{{ $m?->keywords ? implode(', ', $m->keywords) : '-' }}</td></tr>
            </tbody></table></div>
        @if($document->entities->isNotEmpty())
        <div class="card mb-3"><div class="card-header">Dates and amounts found</div>
            <ul class="list-group list-group-flush small">
                @foreach($document->entities->whereIn('type', ['date', 'amount'])->take(15) as $e)<li class="list-group-item">{{ $e->type }}: <strong>{{ $e->value }}</strong> (page {{ $e->page_number }})</li>@endforeach
            </ul></div>@endif
    </div>
    <div class="col-lg-7">
        @if($m?->summary)
        @php $s = $m->summary; @endphp
        <div class="card mb-3"><div class="card-header">Summary <span class="badge bg-warning text-dark">AI-generated, not verified</span>
            <small class="text-muted">{{ $m->summary_generated_at?->diffForHumans() }}</small></div>
            <div class="card-body small">
                <h6>Short summary</h6>
                @foreach($s['short'] ?? [] as $i)<p class="mb-1">{{ $i['text'] }} <a href="{{ route('documents.show', [$document, 'page' => $i['page']]) }}#preview">[p.{{ $i['page'] }}]</a></p>@endforeach
                <h6 class="mt-3">Key points</h6>
                <ul>@foreach($s['key_points'] ?? [] as $i)<li>{{ $i['text'] }} <a href="{{ route('documents.show', [$document, 'page' => $i['page']]) }}#preview">[p.{{ $i['page'] }}]</a></li>@endforeach</ul>
                <details><summary>Detailed summary</summary>
                    @foreach($s['detailed'] ?? [] as $i)<p class="mb-1">{{ $i['text'] }} <span class="text-muted">[p.{{ $i['page'] }}]</span></p>@endforeach</details>
                @if(!empty($s['action_items']))<h6 class="mt-3">Action items stated in the document</h6>
                <ul>@foreach($s['action_items'] as $i)<li>{{ $i['text'] }} <span class="text-muted">[p.{{ $i['page'] }}]</span></li>@endforeach</ul>@endif
                @if(!empty($s['amounts']))<h6 class="mt-3">Financial amounts</h6><p>@foreach($s['amounts'] as $a)<span class="badge bg-light text-dark border">{{ $a['value'] }} p.{{ $a['page'] }}</span> @endforeach</p>@endif
                @if(!empty($s['dates']))<h6>Important dates</h6><p>@foreach($s['dates'] as $a)<span class="badge bg-light text-dark border">{{ $a['value'] }} p.{{ $a['page'] }}</span> @endforeach</p>@endif
            </div></div>
        @endif
        <div class="card" id="preview"><div class="card-header">Preview @if(request('page'))<small class="text-muted">(page {{ (int) request('page') }})</small>@endif</div>
            <iframe title="PDF preview" src="{{ route('documents.preview', $document) }}#page={{ max(1, (int) request('page', 1)) }}" style="width:100%;height:640px;border:0"></iframe></div>
    </div>
</div>
@endsection
