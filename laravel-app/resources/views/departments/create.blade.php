@extends('layouts.app')

@section('title', 'New Department')
@section('page_title', 'Create Department')

@section('content')
<x-page-header title="Create Department" subtitle="Add a new functional unit within your organization" />

<div class="row">
    <div class="col-lg-6">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Department Information</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('departments.store') }}" data-loading>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold text-secondary">Department Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Planning Division">
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Department Active</label>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Create Department</button>
                        <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
