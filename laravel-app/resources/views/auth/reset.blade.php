@extends('layouts.guest')
@section('title', 'New password')
@section('content')
<h2>Choose a new password</h2>
<p class="text-muted mb-4">Use at least 8 characters.</p>
<form method="POST" action="{{ route('password.update') }}" data-loading="Saving...">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $email) }}"></div>
    <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
    <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <button class="btn btn-primary w-100 py-2" type="submit">Update password</button>
</form>
@endsection
