@extends('layouts.app')

@section('title', $document->title)
@section('page_title', 'Document Intelligence View')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-2">
            <x-status :status="$document->processing_status" />
            <span class="badge bg-light text-secondary border">{{ $document->category }}</span>
            @if($document->category_confidence > 0)
                <span class="badge bg-info-subtle text-info border border-info-subtle" title="Heuristic classification score">
                    Confidence: {{ round($document->category_confidence * 100) }}%
                </span>
            @endif
            @if($document->archived_at)
                <span class="badge bg-warning text-dark">Archived</span>
            @endif
        </div>
        <h1 class="h3 fw-bold text-dark mb-1">{{ $document->title }}</h1>
        <p class="text-muted small mb-0">
            {{ $document->original_name }} &bull; {{ number_format($document->size_bytes / 1024, 1) }} KB &bull;
            Uploaded {{ $document->created_at->format('M d, Y H:i') }} by {{ $document->uploader?->name }} &bull;
            Department: {{ $document->department?->name ?? 'Organization-wide' }}
        </p>
    </div>

    <!-- Action Toolbar -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('documents.preview', $document) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Open inline PDF preview">
            <i class="bi bi-eye me-1"></i>Preview PDF
        </a>
        <a href="{{ route('documents.download', $document) }}" class="btn btn-sm btn-outline-secondary" title="Download private file">
            <i class="bi bi-download me-1"></i>Download
        </a>
        <a href="{{ route('documents.summary', $document) }}" class="btn btn-sm btn-outline-info" title="Extractive summary">
            <i class="bi bi-file-text me-1"></i>Summary
        </a>
        <a href="{{ route('documents.related', $document) }}" class="btn btn-sm btn-outline-secondary" title="Centroid similarity search">
            <i class="bi bi-diagram-2 me-1"></i>Related
        </a>

        @if($document->canBeModifiedBy(Auth::user()))
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-sm btn-light border" title="Edit metadata">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <form method="POST" action="{{ route('documents.reprocess', $document) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-light border" title="Re-run OCR and extraction pipeline">
                    <i class="bi bi-arrow-repeat me-1"></i>Reprocess
                </button>
            </form>
            <form method="POST" action="{{ route('documents.archive', $document) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-light border" title="Toggle archive state">
                    <i class="bi {{ $document->archived_at ? 'bi-box-arrow-up' : 'bi-archive' }} me-1"></i>
                    {{ $document->archived_at ? 'Restore' : 'Archive' }}
                </button>
            </form>
        @endif

        @if(Auth::user()->can('delete', $document))
            <form method="POST" action="{{ route('documents.destroy', $document) }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to permanently delete this document from the library?">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </form>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Extracted Information (Unverified) -->
    <div class="col-lg-6">
        <div class="nx-card h-100">
            <div class="nx-card-header d-flex justify-content-between align-items-center">
                <span>Extracted Information <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1">Unverified by Human</span></span>
                <span class="small text-muted">Regex & Model Heuristics</span>
            </div>
            <div class="nx-card-body">
                @php
                    $meta = $document->metadata;
                @endphp
                <table class="table table-sm table-bordered mb-3">
                    <tr>
                        <th class="bg-light w-40">Project Name</th>
                        <td>{!! $meta?->project_name ? e($meta->project_name) : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Reference / Memo No</th>
                        <td>{!! $meta?->reference_number ? e($meta->reference_number) : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Issuing Authority</th>
                        <td>{!! $meta?->issuing_organization ? e($meta->issuing_organization) : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Document Date</th>
                        <td>{!! $meta?->document_date ? e($meta->document_date->format('d F Y')) : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Budget / Financial Allocation</th>
                        <td>{!! $meta?->budget ? '<strong class="text-success">' . e($meta->budget) . '</strong>' : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Completion Deadline</th>
                        <td>{!! $meta?->deadline ? '<strong class="text-primary">' . e($meta->deadline) . '</strong>' : '<em class="text-muted">Unknown</em>' !!}</td>
                    </tr>
                </table>

                <!-- Keywords -->
                @if(!empty($meta?->keywords))
                    <div class="mt-3">
                        <span class="small fw-semibold text-secondary d-block mb-1">Indexed Topic Keywords:</span>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($meta->keywords as $kw)
                                <span class="badge bg-light text-dark border font-monospace">{{ $kw }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Embedded PDF Preview -->
    <div class="col-lg-6">
        <div class="nx-card h-100">
            <div class="nx-card-header d-flex justify-content-between align-items-center">
                <span>PDF Document Preview</span>
                <a href="{{ route('documents.preview', $document) }}" target="_blank" class="small text-primary text-decoration-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i>New Tab
                </a>
            </div>
            <div class="nx-card-body p-0">
                <iframe src="{{ route('documents.preview', $document) }}#page=1" class="w-100 border-0" style="height: 380px;" title="PDF Preview"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Text, Entities, and Processing Jobs -->
<div class="nx-card">
    <div class="nx-card-header p-0">
        <ul class="nav nav-tabs border-0 px-3 pt-2" id="docTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active small fw-semibold" id="pages-tab" data-bs-toggle="tab" data-bs-target="#pages-pane" type="button" role="tab">
                    Extracted Pages ({{ $document->pages->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link small fw-semibold" id="entities-tab" data-bs-toggle="tab" data-bs-target="#entities-pane" type="button" role="tab">
                    Extracted Entities ({{ $document->entities->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link small fw-semibold" id="jobs-tab" data-bs-toggle="tab" data-bs-target="#jobs-pane" type="button" role="tab">
                    Processing Pipeline History
                </button>
            </li>
        </ul>
    </div>
    <div class="nx-card-body">
        <div class="tab-content" id="docTabsContent">
            <!-- Pages Tab -->
            <div class="tab-pane fade show active" id="pages-pane" role="tabpanel">
                @forelse($document->pages as $page)
                    <div class="p-3 border rounded-3 mb-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="fw-bold small">Page {{ $page->page_number }}</span>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.7rem;">
                                Extraction: {{ strtoupper($page->extraction_method) }}
                            </span>
                        </div>
                        <div class="font-monospace small text-dark" style="white-space: pre-wrap; font-size: 0.82rem; line-height: 1.6;">{{ $page->text }}</div>
                    </div>
                @empty
                    <x-empty icon="bi-file-text" title="No page text extracted yet" subtitle="Processing may be pending or failed." />
                @endforelse
            </div>

            <!-- Entities Tab -->
            <div class="tab-pane fade" id="entities-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="nx-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Extracted Value</th>
                                <th>Page</th>
                                <th>Verbatim Evidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($document->entities as $entity)
                                <tr>
                                    <td><span class="badge bg-light text-dark border">{{ $entity->type }}</span></td>
                                    <td class="fw-semibold">{{ $entity->value }}</td>
                                    <td>Page {{ $entity->page_number }}</td>
                                    <td class="small text-muted font-monospace">{{ $entity->evidence ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No structured entities extracted.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Jobs Tab -->
            <div class="tab-pane fade" id="jobs-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="nx-table">
                        <thead>
                            <tr>
                                <th>Attempt</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Finished</th>
                                <th>Error Diagnostics</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($document->processingJobs as $job)
                                <tr>
                                    <td>#{{ $job->attempt }}</td>
                                    <td><x-status :status="$job->status" /></td>
                                    <td class="small">{{ $job->started_at?->format('H:i:s M d') ?? '—' }}</td>
                                    <td class="small">{{ $job->finished_at?->format('H:i:s M d') ?? '—' }}</td>
                                    <td class="small text-danger">{{ $job->error_message ?: 'None' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No processing history recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
