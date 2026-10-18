@extends('layouts.app')
@section('title', 'Search')
@section('content')
<x-page-header title="Search" subtitle="Find documents by keyword, or by meaning. Only documents you can access are searched." />

<form method="GET" class="nx-card nx-card-body mb-3" data-loading="Searching...">
    <div class="nx-searchbar">
        <input class="form-control" name="q" value="{{ $q }}" placeholder="e.g. project deadline, বাজেট, circular" required>
        <div class="btn-group" role="group" aria-label="Search mode">
            <input type="radio" class="btn-check" name="mode" id="m1" value="keyword" @checked($mode === 'keyword')><label class="btn btn-ghost" for="m1"><i class="bi bi-fonts me-1"></i> Keyword</label>
            <input type="radio" class="btn-check" name="mode" id="m2" value="semantic" @checked($mode === 'semantic')><label class="btn btn-ghost" for="m2"><i class="bi bi-stars me-1"></i> Semantic</label>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Search</button>
    </div>
    <div class="row g-2 mt-1">
        <div class="col-md-3"><select name="category" class="form-select form-select-sm"><option value="">All categories</option>@foreach(config('nexora.categories') as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="department_id" class="form-select form-select-sm"><option value="">All my departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}" title="Uploaded from"></div>
        <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}" title="Uploaded to"></div>
    </div>
</form>

@if($error)<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ $error }}</div></div>@endif

@if($q !== '' && !$error)
    <div class="nx-card">
    @if($mode === 'keyword')
        @forelse($results as $d)
            <div class="nx-result">
                <a class="fw-semibold fs-6" href="{{ route('documents.show', $d) }}">{{ $d->title }}</a> <span class="nx-tag ms-1">{{ $d->category }}</span>
                @if($d->hit_snippet)
                    <div class="nx-quote">{{ $d->hit_snippet }}<div class="mt-1"><a href="{{ route('documents.show', [$d, 'page' => $d->hit_page]) }}#preview"><i class="bi bi-box-arrow-up-right"></i> Open page {{ $d->hit_page }}</a></div></div>
                @endif
            </div>
        @empty
            <x-empty icon="bi-search" title="No results">Nothing matched in the documents you can access.</x-empty>
        @endforelse
    @else
        @forelse($semantic as $hit)
            <div class="nx-result">
                <a class="fw-semibold fs-6" href="{{ route('documents.show', $hit['doc']) }}">{{ $hit['doc']->title }}</a>
                <span class="nx-tag ms-1">{{ $hit['doc']->category }}</span> <span class="nx-pill ms-1">relevance {{ $hit['score'] }}</span>
                <div class="nx-quote">{{ $hit['snippet'] }}<div class="mt-1"><a href="{{ route('documents.show', [$hit['doc'], 'page' => $hit['page']]) }}#preview"><i class="bi bi-box-arrow-up-right"></i> Open page {{ $hit['page'] }}</a></div></div>
            </div>
        @empty
            <x-empty icon="bi-search" title="No results">Nothing matched in the documents you can access.</x-empty>
        @endforelse
    @endif
    </div>
    @if($mode === 'keyword' && $results)<div class="mt-3">{{ $results->links('pagination::bootstrap-5') }}</div>@endif
@endif
@endsection
