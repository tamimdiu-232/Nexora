@extends('layouts.guest')
@section('title', Access denied)
@section('content')
<div class="text-center">
    <div class="display-3 fw-bold" style="color:#c7d2fe">403</div>
    <h2>Access denied</h2>
    <p class="text-muted">You do not have permission to open this page or document.</p>
    <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house me-1"></i> Back to start</a>
</div>
@endsection
