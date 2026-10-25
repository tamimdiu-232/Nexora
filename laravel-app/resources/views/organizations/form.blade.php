@extends('layouts.app')
@section('title', 'Organization')
@section('content')
<x-page-header :title="$organization->exists ? 'Edit organization' : 'Add organization'" />
<form method="POST" action="{{ $organization->exists ? route('organizations.update', $organization) : route('organizations.store') }}" class="nx-card nx-card-body" data-loading="Saving...">
    @csrf @if($organization->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $organization->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Code</label><input name="code" class="form-control" required value="{{ old('code', $organization->code) }}"><div class="form-text">Letters, numbers, dashes and underscores.</div></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $organization->is_active))>
            <label class="form-check-label" for="active">Active <span class="text-muted">(a suspended organization's users cannot sign in)</span></label></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="{{ route('organizations.index') }}">Cancel</a></div>
</form>
@endsection
