@extends('layouts.guest')

@section('title', 'Forgot Password')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold text-dark mb-1">Reset Password</h3>
    <p class="text-muted small">Enter your account email. If active, a secure password recovery link will be dispatched.</p>
</div>

<form method="POST" action="{{ route('password.email') }}" data-loading>
    @csrf

    <div class="mb-4">
        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@organization.gov">
        </div>
        @error('email')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium shadow-sm mb-3">
        <i class="bi bi-send me-2"></i>Send Password Reset Link
    </button>

    <div class="text-center">
        <a href="{{ route('login') }}" class="small text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>Back to Sign In
        </a>
    </div>
</form>
@endsection
