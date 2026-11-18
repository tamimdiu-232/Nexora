@extends('layouts.guest')

@section('title', 'Set New Password')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold text-dark mb-1">Set New Password</h3>
    <p class="text-muted small">Choose a strong, complex password for your account.</p>
</div>

<form method="POST" action="{{ route('password.update') }}" data-loading>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ $email ?? old('email') }}" required autofocus>
        @error('email')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required placeholder="At least 8 characters">
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required placeholder="Repeat new password">
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium shadow-sm mb-3">
        <i class="bi bi-shield-check me-2"></i>Update Password and Sign In
    </button>
</form>
@endsection
