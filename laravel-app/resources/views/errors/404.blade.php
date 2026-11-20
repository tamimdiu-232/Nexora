@extends('layouts.guest')

@section('title', '404 Not Found')

@section('content')
<div class="text-center py-4">
    <div class="display-3 text-secondary fw-bold mb-2">404</div>
    <h4 class="fw-bold text-dark mb-2">Resource Not Found</h4>
    <p class="text-muted small mb-4">
        The requested document, conversation, or platform record could not be found or has been moved.
    </p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
        <i class="bi bi-arrow-left me-2"></i>Return to Workspace
    </a>
</div>
@endsection
