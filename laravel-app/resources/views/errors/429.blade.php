@extends('layouts.guest')

@section('title', '429 Rate Limit Exceeded')

@section('content')
<div class="text-center py-4">
    <div class="display-3 text-warning fw-bold mb-2">429</div>
    <h4 class="fw-bold text-dark mb-2">Too Many Requests</h4>
    <p class="text-muted small mb-4">
        You have exceeded the allowable request rate limit. Please wait a moment before trying again.
    </p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
        <i class="bi bi-arrow-left me-2"></i>Return Home
    </a>
</div>
@endsection
