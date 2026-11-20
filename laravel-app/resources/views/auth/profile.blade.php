@extends('layouts.app')

@section('title', 'My Profile')
@section('page_title', 'Account Profile')

@section('content')
<x-page-header title="My Profile" subtitle="Manage your account profile and institutional credentials" />

<div class="row">
    <div class="col-lg-7">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Account Information</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('profile.update') }}" data-loading>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold text-secondary">Full Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
                        <input type="email" class="form-control bg-light" id="email" value="{{ $user->email }}" readonly disabled>
                        <div class="form-text small">Email modification requires system administrator approval.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">System Role</label>
                            <input type="text" class="form-control bg-light" value="{{ config('nexora.roles.' . $user->role, $user->role) }}" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Organization</label>
                            <input type="text" class="form-control bg-light" value="{{ $user->organization?->name ?? 'Platform Operations' }}" readonly disabled>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Change Password</h6>

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-semibold text-secondary">Current Password</label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" placeholder="Required if changing password">
                        @error('current_password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label small fw-semibold text-secondary">New Password</label>
                            <input type="password" class="form-control @error('new_password') is-invalid @enderror" id="new_password" name="new_password" placeholder="At least 8 characters">
                            @error('new_password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="new_password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                            <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" placeholder="Repeat new password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
