@extends('layouts.app')

@section('title', 'Institutional Search')
@section('page_title', 'Institutional Search & Retrieval')

@section('content')
<x-page-header title="Document Search" subtitle="Lexical keyword matching and semantic vector retrieval across institutional files" />

<!-- Search Bar Box -->
<div class="nx-card mb-4 shadow-sm">
    <div class="nx-card-body p-4">
        <form method="GET" action="{{ route('search.index') }}">
            <!-- Mode Toggle & Query Input -->
            <div class="mb-3">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search fs-5"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0 fs-6" placeholder="Enter keywords or ask in English/Bangla (e.g. riverside connector budget, ডিজিটাল ফাইলিং)..." value="{{ $queryStr }}" autofocus required>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Search</button>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2 border-top">
                <!-- Segmented Mode Control -->
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="mode" id="modeKeyword" value="keyword" {{ $mode !== 'semantic' ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-primary px-3" for="modeKeyword">
                        <i class="bi bi-fonts me-1"></i>Keyword Search
                    </label>

                    <input type="radio" class="btn-check" name="mode" id="modeSemantic" value="semantic" {{ $mode === 'semantic' ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="btn btn-outline-primary px-3" for="modeSemantic">
                        <i class="bi bi-stars me-1"></i>Semantic Vector Search
                    </label>
                </div>

                <!-- Filters -->
                <div class="d-flex align-items-center gap-2">
                    <select name="category" class="form-select form-select-sm" style="max-width: 180px;">
                        <option value="">All Categories</option>
                        @foreach(config('nexora.categories', []) as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>

                    @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                        <select name="department_id" class="form-select form-select-sm" style="max-width: 180px;">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results Section -->
@if(!empty($queryStr))
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">
            @if($mode === 'semantic')
                Semantic Search Matches (Top {{ count($results) }})
            @else
                Lexical Keyword Matches ({{ $paginatedResults ? $paginatedResults->total() : 0 }} occurrences)
            @endif
        </h6>
        <span class="small text-muted font-monospace">Mode: {{ strtoupper($mode) }}</span>
    </div>

    @if($mode === 'semantic')
        <!-- Semantic Results -->
        <div class="d-flex flex-column gap-3 mb-4">
            @forelse($results as $item)
                @php
                    $doc = $item['document'];
                @endphp
                <div class="nx-card p-3 nx-card-hover">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <a href="{{ route('documents.show', $doc) }}" class="h6 fw-bold text-dark text-decoration-none hover-text-primary mb-1 d-block">
                                {{ $doc->title }}
                            </a>
                            <div class="small text-muted">
                                {{ $doc->department?->name ?? 'Organization-wide' }} &bull;
                                Category: {{ $doc->category }} &bull;
                                {{ $doc->original_name }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                Score: {{ round($item['score'] * 100) }}%
                            </span>
                            <a href="{{ route('documents.preview', $doc) }}#page={{ $item['page_number'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open Page {{ $item['page_number'] }}
                            </a>
                        </div>
                    </div>
                    <blockquote class="blockquote small bg-light p-3 rounded-2 border-start border-3 border-primary mb-0 text-secondary font-monospace" style="font-size: 0.84rem; line-height: 1.6;">
                        {{ $item['snippet'] }}
                    </blockquote>
                </div>
            @empty
                <div class="nx-card p-5 text-center">
                    <x-empty icon="bi-search" title="No semantic matches found" subtitle="Try broader inquiry terms or switch to keyword mode." />
                </div>
            @endforelse
        </div>
    @else
        <!-- Keyword Results -->
        <div class="d-flex flex-column gap-3 mb-4">
            @forelse($paginatedResults as $match)
                @php
                    $doc = $match->document;
                @endphp
                <div class="nx-card p-3 nx-card-hover">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <a href="{{ route('documents.show', $doc) }}" class="h6 fw-bold text-dark text-decoration-none hover-text-primary mb-1 d-block">
                                {{ $doc->title }}
                            </a>
                            <div class="small text-muted">
                                {{ $doc->department?->name ?? 'Organization-wide' }} &bull;
                                Category: {{ $doc->category }}
                            </div>
                        </div>
                        <a href="{{ route('documents.preview', $doc) }}#page={{ $match->page_number }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open Page {{ $match->page_number }}
                        </a>
                    </div>
                    <blockquote class="blockquote small bg-light p-3 rounded-2 border-start border-3 border-secondary mb-0 text-secondary font-monospace" style="font-size: 0.84rem; line-height: 1.6;">
                        {{ Str::limit($match->text, 280) }}
                    </blockquote>
                </div>
            @empty
                <div class="nx-card p-5 text-center">
                    <x-empty icon="bi-search" title="No exact keyword matches found" subtitle="Try alternate spellings or toggle to semantic mode." />
                </div>
            @endforelse
        </div>

        @if($paginatedResults && $paginatedResults->hasPages())
            <div class="p-3 border-top">
                {{ $paginatedResults->links() }}
            </div>
        @endif
    @endif
@endif
@endsection
