@extends('layouts.app')
@section('title', 'Department')
@section('content')
<h3>{{ $department->exists ? 'Edit department' : 'Add department' }}</h3>
<form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" class="card card-body" data-loading="Saving...">
    @csrf @if($department->exists) @method('PUT') @endif
    <div class="row g-3">
        @unless($department->exists)
        <div class="col-md-6"><label class="form-label">Organization</label><select name="organization_id" class="form-select" required>@foreach($organizations as $o)<option value="{{ $o->id }}" @selected(old('organization_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        @endunless
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $department->name) }}"></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $department->is_active))><label class="form-check-label" for="act">Active</label></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Save</button> <a class="btn btn-link" href="{{ route('departments.index') }}">Cancel</a></div>
</form>
@endsection
