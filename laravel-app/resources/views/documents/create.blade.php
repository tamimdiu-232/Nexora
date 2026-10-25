@extends('layouts.app')
@section('title', 'Upload document')
@section('content')
<x-page-header title="Upload a document" subtitle="PDFs are read, classified and indexed automatically (scanned pages use OCR)." />
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="nx-card nx-card-body" data-loading="Uploading...">
    @csrf
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">PDF file <span class="text-muted fw-normal">(max {{ config('nexora.max_upload_kb') / 1024 }} MB)</span></label>
            <input type="file" name="file" accept="application/pdf" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Title</label><input name="title" value="{{ old('title') }}" class="form-control" required maxlength="255"></div>
        <div class="col-md-6"><label class="form-label">Department</label>
            <select name="department_id" class="form-select">
                @if(auth()->user()->isOrgAdmin())<option value="">Organization-wide (everyone in the organization)</option>@endif
                @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
            </select>
            <div class="form-text">Only members of the department (and Organization Admins) can see department documents.</div></div>
        <div class="col-md-6"><label class="form-label">Category</label>
            <select name="category" class="form-select"><option value="">Let AI classify</option>
                @foreach(config('nexora.categories') as $c)<option @selected(old('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Tags</label><input name="tags" value="{{ old('tags') }}" class="form-control" maxlength="255" placeholder="comma separated"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control" maxlength="5000">{{ old('description') }}</textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-up me-1"></i> Upload and process</button>
        <a class="btn btn-ghost" href="{{ route('documents.index') }}">Cancel</a></div>
</form>
@endsection
