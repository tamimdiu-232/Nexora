@extends('layouts.app')
@section('title', 'Upload document')
@section('content')
<h3>Upload document</h3>
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card card-body" data-loading="Uploading...">
    @csrf
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">PDF file (max {{ config('govdoc.max_upload_kb') / 1024 }} MB)</label>
            <input type="file" name="file" accept="application/pdf" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Title</label><input name="title" value="{{ old('title') }}" class="form-control" required maxlength="255"></div>
        @if(auth()->user()->isSuper())
        <div class="col-md-6"><label class="form-label">Organization</label><select name="organization_id" class="form-select" required>
            <option value="">Select...</option>@foreach($organizations as $o)<option value="{{ $o->id }}" @selected(old('organization_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select">
            @if(auth()->user()->isSuper() || auth()->user()->isOrgAdmin())<option value="">Organization-wide (visible to the whole organization)</option>@endif
            @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}@if(auth()->user()->isSuper()) ({{ $d->organization->name }})@endif</option>@endforeach</select>
            <div class="form-text">Visibility follows the department. Non-admin users upload into their own department.</div></div>
        <div class="col-md-6"><label class="form-label">Category (optional; AI will predict if empty)</label><select name="category" class="form-select"><option value="">Let AI classify</option>
            @foreach(config('govdoc.categories') as $c)<option @selected(old('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Tags (comma separated)</label><input name="tags" value="{{ old('tags') }}" class="form-control" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control" maxlength="5000">{{ old('description') }}</textarea></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Upload and process</button> <a class="btn btn-link" href="{{ route('documents.index') }}">Cancel</a></div>
</form>
@endsection
