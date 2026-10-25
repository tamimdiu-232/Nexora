@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<h2>Reset your password</h2>
<p class="text-muted mb-4">Enter your account email and we will send you a reset link.</p>
<form method="POST" action="{{ route('password.email') }}" data-loading="Sending...">
    @csrf
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
    <button class="btn btn-primary w-100 py-2" type="submit">Send reset link</button>
</form>
<p class="small mt-3 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
<p class="text-muted small mt-3 mb-0">Development note: with <code>MAIL_MAILER=log</code> the link is written to <code>storage/logs/laravel.log</code>.</p>
@endsection
