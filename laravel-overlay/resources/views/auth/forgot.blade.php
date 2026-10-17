@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="row justify-content-center mt-4"><div class="col-md-5 col-lg-4"><div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Reset password</h4>
    <form method="POST" action="{{ route('password.email') }}" data-loading="Sending...">@csrf
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
        <button class="btn btn-primary w-100" type="submit">Send reset link</button>
    </form>
    <p class="small mt-3 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
    <p class="text-muted small mt-2 mb-0">Development note: with the default <code>MAIL_MAILER=log</code> the link is written to <code>storage/logs/laravel.log</code>.</p>
</div></div></div></div>
@endsection
