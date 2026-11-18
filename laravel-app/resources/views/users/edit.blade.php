@extends('layouts.app')

@section('title', 'Edit ' . $user->name)
@section('page_title', 'Edit User Settings')

@section('content')
<x-page-header title="Edit User Settings" subtitle="Update role assignments, department placement, or credentials for: {{ $user->name }}" />

<div class="row">
    <div class="col-lg-7">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Account Profile</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('users.update', $user) }}" data-loading>
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold text-secondary">Full Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Email Address</label>
                            <input type="email" class="form-control bg-light" value="{{ $user->email }}" readonly disabled>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label small fw-semibold text-secondary">System Role</label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                @foreach($allowedRoles as $roleKey => $roleLabel)
                                    <option value="{{ $roleKey }}" {{ old('role', $user->role) === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                            <div class="col-md-6">
                                <label for="department_id" class="form-label small fw-semibold text-secondary">Department Assignment</label>
                                <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                    <option value="">-- Organization-wide (No Dept) --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold text-secondary">Reset Password (Optional)</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Leave empty to keep existing password">
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold text-secondary" for="is_active">Account Active</label>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
