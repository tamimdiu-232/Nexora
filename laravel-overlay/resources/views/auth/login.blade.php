@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="row justify-content-center mt-4">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Sign in</h4>
                <form method="POST" action="{{ route('login') }}" data-loading="Signing in...">
                    @csrf
                    <div class="mb-3"><label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
                    <div class="mb-3"><label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required></div>
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>
                <p class="small mt-3 mb-0"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
