@extends('layouts.app')

@section('title', 'New Organization')
@section('page_title', 'Register New Organization')

@section('content')
<x-page-header title="Register Organization" subtitle="Create a new institutional tenant on the Nexora platform" />

<div class="row">
    <div class="col-lg-6">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Organization Details</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('organizations.store') }}" data-loading>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold text-secondary">Organization Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Ministry of Infrastructure">
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label small fw-semibold text-secondary">Identifier Code</label>
                        <input type="text" class="form-control font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required placeholder="e.g. MOI_SYNTHETIC">
                        <div class="form-text small">Unique alphanumeric code used for tenant references.</div>
                        @error('code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Activate immediately</label>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Create Organization</button>
                        <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
