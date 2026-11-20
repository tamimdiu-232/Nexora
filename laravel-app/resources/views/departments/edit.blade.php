@extends('layouts.app')

@section('title', 'Edit ' . $department->name)
@section('page_title', 'Edit Department')

@section('content')
<x-page-header title="Edit Department" subtitle="Update department name and operational status" />

<div class="row">
    <div class="col-lg-6">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Department Settings</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('departments.update', $department) }}" data-loading>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold text-secondary">Department Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $department->name) }}" required>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Department Active</label>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
