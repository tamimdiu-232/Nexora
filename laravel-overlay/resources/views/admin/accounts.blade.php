@extends('layouts.app')
@section('title', 'Account recovery')
@section('content')
<x-page-header title="Account recovery" subtitle="Help people regain access, or lock an account during an incident. Every action is written to the audit log." />
<form class="mb-3" method="GET"><div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search name or email"><button class="btn btn-ghost">Search</button></div></form>

<div class="nx-card">
    @if($users->isEmpty())
        <x-empty icon="bi-key" title="No accounts found">Try a different search.</x-empty>
    @else
    <div class="table-responsive"><table class="nx-table">
        <thead><tr><th>Account</th><th>Organization</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach($users as $account)
            <tr>
                <td><div class="title">{{ $account->name }}</div><div class="small text-muted">{{ $account->email }}</div></td>
                <td>{{ $account->organization?->name ?? 'Platform' }}</td><td>{{ $account->roleLabel() }}</td>
                <td><x-status :status="$account->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end"><div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                    <form method="POST" action="{{ route('admin.accounts.reset-link', $account) }}" data-loading="Sending...">@csrf
                        <button class="btn btn-ghost btn-sm" type="submit" title="Send password reset link"><i class="bi bi-envelope-at"></i> Reset link</button></form>
                    <form method="POST" action="{{ route('admin.accounts.revoke', $account) }}" data-confirm="Sign this account out of every device?">@csrf
                        <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
                    <form method="POST" action="{{ route('admin.accounts.toggle', $account) }}" data-confirm="{{ $account->is_active ? 'Deactivate this account and sign it out?' : 'Re-activate this account?' }}">@csrf
                        <button class="btn btn-sm {{ $account->is_active ? 'btn-ghost text-danger' : 'btn-soft' }}" type="submit"><i class="bi bi-{{ $account->is_active ? 'slash-circle' : 'check-circle' }}"></i> {{ $account->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
