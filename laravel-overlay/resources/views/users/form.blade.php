@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Add user')
@section('content')
<h3>{{ $user->exists ? 'Edit user' : 'Add user' }}</h3>
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card card-body" data-loading="Saving...">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="{{ old('name', $user->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}"></div>
        <div class="col-md-6"><label class="form-label">Password @if($user->exists)<small class="text-muted">(leave blank to keep)</small>@endif</label><input type="password" name="password" class="form-control" minlength="8" @required(!$user->exists)></div>
        <div class="col-md-6"><label class="form-label">Role</label><select name="role" class="form-select">@foreach($roles as $k => $label)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $label }}</option>@endforeach</select></div>
        @if(auth()->user()->isSuper())
        <div class="col-md-6"><label class="form-label">Organization</label><select name="organization_id" class="form-select"><option value="">- none (Super Admin only) -</option>
            @foreach($organizations as $o)<option value="{{ $o->id }}" @selected(old('organization_id', $user->organization_id) == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">- none -</option>
            @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}@if(auth()->user()->isSuper() && $d->organization) ({{ $d->organization->name }})@endif</option>@endforeach</select>
            <div class="form-text">Officers and Department Admins need a department.</div></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="act">Account active</label></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Save</button> <a class="btn btn-link" href="{{ route('users.index') }}">Cancel</a></div>
</form>
@endsection
