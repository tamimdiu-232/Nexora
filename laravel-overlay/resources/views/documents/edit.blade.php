@extends('layouts.app')
@section('title', 'Edit document')
@section('content')
@php $m = $document->metadata; @endphp
<h3>Edit: {{ $document->title }}</h3>
<form method="POST" action="{{ route('documents.update', $document) }}" class="card card-body" data-loading="Saving...">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required value="{{ old('title', $document->title) }}"></div>
        <div class="col-md-6"><label class="form-label">Category (correct the AI prediction here)</label><select name="category" class="form-select">
            @foreach(config('govdoc.categories') as $c)<option @selected(old('category', $document->category) === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Tags</label><input name="tags" class="form-control" value="{{ old('tags', $document->tags) }}"></div>
        @if(auth()->user()->isSuper() || auth()->user()->isOrgAdmin())
        <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Organization-wide</option>
            @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $document->department_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control">{{ old('description', $document->description) }}</textarea></div>
        @foreach(['issuing_organization' => 'Issuing organization', 'reference_number' => 'Reference number', 'document_date' => 'Document date', 'project_name' => 'Project name', 'budget' => 'Budget', 'deadline' => 'Deadline'] as $f => $label)
        <div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $f }}" class="form-control" value="{{ old($f, $m?->$f) }}"></div>
        @endforeach
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Save</button> <a class="btn btn-link" href="{{ route('documents.show', $document) }}">Cancel</a></div>
</form>
@endsection
