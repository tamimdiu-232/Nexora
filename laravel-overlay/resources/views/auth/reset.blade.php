@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<div class="row justify-content-center mt-4"><div class="col-md-5 col-lg-4"><div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Choose a new password</h4>
    <form method="POST" action="{{ route('password.update') }}" data-loading="Saving...">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $email) }}"></div>
        <div class="mb-3"><label class="form-label">New password (min 8)</label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div>
        <button class="btn btn-primary w-100" type="submit">Update password</button>
    </form>
</div></div></div></div>
@endsection
