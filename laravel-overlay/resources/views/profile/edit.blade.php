@extends('layouts.app')
@section('title', 'Profile')
@section('content')
<h3>My profile</h3>
<form method="POST" action="{{ route('profile.update') }}" class="card card-body col-lg-6" data-loading="Saving...">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $user->name) }}"></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}"></div>
    <p class="text-muted small">Role: {{ $user->roleLabel() }} | Organization: {{ $user->organization?->name ?? '-' }} | Department: {{ $user->department?->name ?? '-' }}</p>
    <hr><h6>Change password (optional)</h6>
    <div class="mb-3"><input type="password" name="current_password" class="form-control" placeholder="Current password"></div>
    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="New password (min 8)"></div>
    <div class="mb-3"><input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password"></div>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@endsection
