@extends('layouts.guest')

@section('title', '419 Page Expired')

@section('content')
<div class="text-center py-4">
    <div class="display-3 text-warning fw-bold mb-2">419</div>
    <h4 class="fw-bold text-dark mb-2">Page Expired</h4>
    <p class="text-muted small mb-4">
        Your security session has expired. Please refresh the page and try again.
    </p>
    <a href="{{ route('login') }}" class="btn btn-primary px-4">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Again
    </a>
</div>
@endsection
