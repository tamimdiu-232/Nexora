@extends('layouts.app')
@section('title', 'Department')
@section('content')
<x-page-header :title="$department->exists ? 'Edit department' : 'Add department'" />
<form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" class="nx-card nx-card-body" data-loading="Saving...">
    @csrf @if($department->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $department->name) }}"></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $department->is_active))><label class="form-check-label" for="active">Active</label></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="{{ route('departments.index') }}">Cancel</a></div>
</form>
@endsection
