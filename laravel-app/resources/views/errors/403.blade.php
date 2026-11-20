@extends('layouts.guest')

@section('title', '403 Forbidden')

@section('content')
<div class="text-center py-4">
    <div class="display-3 text-danger fw-bold mb-2">403</div>
    <h4 class="fw-bold text-dark mb-2">Access Prohibited</h4>
    <p class="text-muted small mb-4">
        {{ $exception->getMessage() ?: 'You do not have administrative permission or departmental clearance to view this document or resource.' }}
    </p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
        <i class="bi bi-arrow-left me-2"></i>Return to Workspace
    </a>
</div>
@endsection
