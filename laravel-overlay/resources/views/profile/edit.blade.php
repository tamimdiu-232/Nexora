@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<x-page-header title="My profile" :subtitle="$user->roleLabel() . ' · ' . ($user->organization?->name ?? 'Platform') . ($user->department ? ' · ' . $user->department->name : '')" />
<form method="POST" action="{{ route('profile.update') }}" class="nx-card nx-card-body" style="max-width:640px" data-loading="Saving...">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $user->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}"></div>
        <div class="col-12"><hr class="my-1"><div class="fw-semibold">Change password <span class="text-muted fw-normal">(optional)</span></div></div>
        <div class="col-12"><input type="password" name="current_password" class="form-control" placeholder="Current password"></div>
        <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="New password (min 8)"></div>
        <div class="col-md-6"><input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary" type="submit">Save changes</button></div>
</form>
@endsection
