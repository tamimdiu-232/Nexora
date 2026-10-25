@extends('layouts.app')
@section('title', $user->exists ? 'Edit account' : 'Add account')
@section('content')
@php $actor = auth()->user(); @endphp
<x-page-header :title="$user->exists ? 'Edit account' : 'Add account'" :subtitle="$actor->isSuper() ? 'Platform admins create Organization Admins; they then manage their own people.' : null" />
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="nx-card nx-card-body" data-loading="Saving...">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $user->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}"></div>
        <div class="col-md-6"><label class="form-label">Password @if($user->exists)<span class="text-muted fw-normal">(leave blank to keep)</span>@endif</label>
            <input type="password" name="password" class="form-control" minlength="8" @required(!$user->exists)></div>
        <div class="col-md-6"><label class="form-label">Role</label>
            <select name="role" class="form-select">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(old('role', $user->role) === $key)>{{ $label }}</option>@endforeach</select></div>
        @if($actor->isSuper())
            <div class="col-md-6"><label class="form-label">Organization</label>
                <select name="organization_id" class="form-select" required><option value="">Select an organization</option>
                    @foreach($organizations as $o)<option value="{{ $o->id }}" @selected(old('organization_id', $user->organization_id) == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        @else
            <div class="col-md-6"><label class="form-label">Department</label>
                <select name="department_id" class="form-select"><option value="">None</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>@endforeach</select>
                <div class="form-text">Officers and Department Admins need a department.</div></div>
        @endif
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="active">Account is active</label></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="{{ route('users.index') }}">Cancel</a></div>
</form>
@endsection
