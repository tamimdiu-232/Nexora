@extends('layouts.app')

@section('title', 'New User')
@section('page_title', 'Create User Account')

@section('content')
<x-page-header title="Create User Account" subtitle="Provision credentials and departmental role permissions" />

<div class="row">
    <div class="col-lg-7">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Account Credentials & Scope</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('users.store') }}" data-loading>
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Ayesha Rahman">
                            @error('name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required placeholder="e.g. ayesha@demo-a.test">
                            @error('email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold text-secondary">Initial Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required placeholder="Minimum 8 characters">
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label small fw-semibold text-secondary">System Role</label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                <option value="">-- Select Role --</option>
                                @foreach($allowedRoles as $roleKey => $roleLabel)
                                    <option value="{{ $roleKey }}" {{ old('role') === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(Auth::user()->isSuperAdmin() && count($organizations) > 0)
                            <div class="col-md-6">
                                <label for="organization_id" class="form-label small fw-semibold text-secondary">Assigned Organization</label>
                                <select class="form-select @error('organization_id') is-invalid @enderror" id="organization_id" name="organization_id" required>
                                    <option value="">-- Select Organization --</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                    @endforeach
                                </select>
                                @error('organization_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                            <div class="col-md-6">
                                <label for="department_id" class="form-label small fw-semibold text-secondary">Department Assignment</label>
                                <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                    <option value="">-- Organization-wide (No Dept) --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text small">Required for Officer and Department Admin roles.</div>
                                @error('department_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Account Active</label>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Create User</button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
