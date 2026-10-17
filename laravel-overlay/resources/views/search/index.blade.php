@extends('layouts.app')
@section('title', 'Search')
@section('content')
<h3>Search documents</h3>
<form method="GET" class="card card-body mb-3" data-loading="Searching...">
    <div class="row g-2">
        <div class="col-md-5"><input class="form-control" name="q" value="{{ $q }}" placeholder="e.g. project deadline / বাজেট" required></div>
        <div class="col-md-2"><select name="mode" class="form-select"><option value="keyword" @selected($mode === 'keyword')>Keyword</option><option value="semantic" @selected($mode === 'semantic')>Semantic (AI)</option></select></div>
        <div class="col-md-2"><select name="category" class="form-select"><option value="">All categories</option>@foreach(config('govdoc.categories') as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="department_id" class="form-select"><option value="">All my departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" class="form-control" name="from" value="{{ request('from') }}" title="Uploaded from"></div>
        <div class="col-md-2"><input type="date" class="form-control" name="to" value="{{ request('to') }}" title="Uploaded to"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Search</button></div>
    </div>
</form>
@if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
@if($q !== '' && !$error)
    @if($mode === 'keyword')
        @forelse($results as $d)
        <div class="card mb-2"><div class="card-body">
            <a class="fs-5" href="{{ route('documents.show', $d) }}">{{ $d->title }}</a> <span class="badge bg-light text-dark border">{{ $d->category }}</span>
            @if($d->hit_snippet)<div class="citation p-2 mt-2 small"><pre class="snippet">{{ $d->hit_snippet }}</pre>
                <a href="{{ route('documents.show', [$d, 'page' => $d->hit_page]) }}#preview">Open page {{ $d->hit_page }}</a></div>@endif
        </div></div>
        @empty<div class="text-muted">No results found in documents you can access.</div>@endforelse
        <div class="mt-3">{{ $results->links('pagination::bootstrap-5') }}</div>
    @else
        <p class="text-muted small">Top matches by meaning, ranked by relevance (hash-based baseline embeddings). Showing at most 20.</p>
        @forelse($semantic as $h)
        <div class="card mb-2"><div class="card-body">
            <a class="fs-5" href="{{ route('documents.show', $h['doc']) }}">{{ $h['doc']->title }}</a> <span class="badge bg-light text-dark border">{{ $h['doc']->category }}</span>
            <span class="badge bg-secondary">relevance {{ $h['score'] }}</span>
            <div class="citation p-2 mt-2 small"><pre class="snippet">{{ $h['snippet'] }}</pre>
                <a href="{{ route('documents.show', [$h['doc'], 'page' => $h['page']]) }}#preview">Open page {{ $h['page'] }}</a></div>
        </div></div>
        @empty<div class="text-muted">No results found in documents you can access.</div>@endforelse
    @endif
@endif
@endsection
