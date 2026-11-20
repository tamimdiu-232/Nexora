@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold text-dark mb-1">Sign In</h3>
    <p class="text-muted small">Enter your institutional credentials to access your organization's document intelligence workspace.</p>
</div>

<form method="POST" action="{{ route('login') }}" data-loading>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@organization.gov">
        </div>
        @error('email')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
            <a href="{{ route('password.request') }}" class="small text-decoration-none text-primary">Forgot password?</a>
        </div>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control border-start-0 ps-0 @error('password') is-invalid @enderror" id="password" name="password" required placeholder="••••••••">
        </div>
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label small text-muted" for="remember">Keep me signed in on this workstation</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium shadow-sm">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Nexora
    </button>
</form>
@endsection
