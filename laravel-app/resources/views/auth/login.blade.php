@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<h2>Welcome back</h2>
<p class="text-muted mb-4">Sign in to your {{ config('nexora.brand.name') }} workspace.</p>
<form method="POST" action="{{ route('login') }}" data-loading="Signing in...">
    @csrf
    <div class="mb-3"><label class="form-label" for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="you@organization.org" required autofocus></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
        <input id="password" type="password" name="password" class="form-control" required></div>
    <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
</form>
<p class="small mt-3 mb-0"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
@endsection
