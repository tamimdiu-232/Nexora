@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<x-page-header :title="request('archived') ? 'Archived documents' : 'Documents'" subtitle="Everything you are allowed to see, searchable and organized.">
    <a class="btn btn-ghost" href="{{ route('documents.index', ['archived' => request('archived') ? null : 1]) }}"><i class="bi bi-archive me-1"></i> {{ request('archived') ? 'Show active' : 'Show archived' }}</a>
    @can('create', App\Models\Document::class)<a class="btn btn-primary" href="{{ route('documents.create') }}"><i class="bi bi-cloud-arrow-up me-1"></i> Upload</a>@endcan
</x-page-header>

<form class="nx-card nx-card-body mb-3" method="GET">
    <input type="hidden" name="archived" value="{{ request('archived') }}">
    <div class="row g-2">
        <div class="col-lg-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search title, tags or description"></div>
        <div class="col-md-4 col-lg-3"><select class="form-select" name="category"><option value="">All categories</option>
            @foreach(config('nexora.categories') as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-4 col-lg-2"><select class="form-select" name="status"><option value="">Any status</option>
            @foreach(['pending', 'processing', 'completed', 'failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div class="col-md-4 col-lg-2"><select class="form-select" name="department_id"><option value="">All departments</option>
            @foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-lg-1"><button class="btn btn-soft w-100"><i class="bi bi-funnel"></i></button></div>
    </div>
</form>

<div class="nx-card">
    @if($documents->isEmpty())
        <x-empty icon="bi-folder2-open" title="No documents found">Try different filters, or upload a document.</x-empty>
    @else
        <div class="table-responsive">
            <table class="nx-table">
                <thead><tr><th>Document</th><th>Category</th><th>Department</th><th>Status</th><th>Uploaded</th></tr></thead>
                <tbody>
                @foreach($documents as $d)
                    <tr>
                        <td><a class="title" href="{{ route('documents.show', $d) }}">{{ $d->title }}</a></td>
                        <td><span class="nx-tag">{{ $d->category }}</span></td>
                        <td class="text-muted">{{ $d->department?->name ?? 'Organization-wide' }}</td>
                        <td><x-status :status="$d->processing_status" /></td>
                        <td class="text-muted">{{ $d->created_at->format('d M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
@endsection
