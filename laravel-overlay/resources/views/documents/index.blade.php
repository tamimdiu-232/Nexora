@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Document library @if(request('archived'))<small class="text-muted">(archived)</small>@endif</h3>
    @can('create', App\Models\Document::class)<a class="btn btn-primary" href="{{ route('documents.create') }}">Upload document</a>@endcan
</div>
<form class="card card-body mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Title, tags, description"></div>
        <div class="col-md-2"><select class="form-select" name="category"><option value="">All categories</option>
            @foreach(config('govdoc.categories') as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="status"><option value="">Any status</option>
            @foreach(['pending','processing','completed','failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="department_id"><option value="">All departments</option>
            @foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-md-1 form-check pt-2 ps-4"><input class="form-check-input" type="checkbox" name="archived" value="1" id="arch" @checked(request('archived'))><label class="form-check-label" for="arch">Archived</label></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
    </div>
</form>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Title</th><th>Category</th><th>Department</th><th>Status</th><th>Uploaded</th></tr></thead>
    <tbody>
    @forelse($documents as $d)
        <tr><td><a href="{{ route('documents.show', $d) }}">{{ $d->title }}</a></td><td>{{ $d->category }}</td>
            <td>{{ $d->department?->name ?? 'Organization-wide' }}</td><td>@include('partials.status', ['status' => $d->processing_status])</td>
            <td>{{ $d->created_at->format('Y-m-d') }}</td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No documents found.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
@endsection
