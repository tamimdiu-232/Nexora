@extends('layouts.guest')
@section('title', Session expired)
@section('content')
<div class="text-center">
    <div class="display-3 fw-bold" style="color:#c7d2fe">419</div>
    <h2>Session expired</h2>
    <p class="text-muted">Your session expired. Reload the page and try again.</p>
    <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house me-1"></i> Back to start</a>
</div>
@endsection
