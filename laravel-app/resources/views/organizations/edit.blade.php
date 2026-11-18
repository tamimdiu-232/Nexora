@extends('layouts.app')

@section('title', 'Edit ' . $organization->name)
@section('page_title', 'Edit Organization')

@section('content')
<x-page-header title="Edit Organization" subtitle="Update organization details or toggle tenant activation status" />

<div class="row">
    <div class="col-lg-6">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Tenant Configuration</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('organizations.update', $organization) }}" data-loading>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold text-secondary">Organization Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $organization->name) }}" required>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label small fw-semibold text-secondary">Identifier Code</label>
                        <input type="text" class="form-control font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $organization->code) }}" required>
                        @error('code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-check form-switch p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $organization->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold text-dark" for="is_active">Tenant Account Active</label>
                        <div class="form-text small text-danger mt-1">
                            <strong>Incident Response Lever:</strong> Unticking this suspends the organization. All organization users will be logged out immediately and prevented from signing in until re-activated.
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
