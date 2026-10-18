@extends('layouts.guest')
@section('title', Not found)
@section('content')
<div class="text-center">
    <div class="display-3 fw-bold" style="color:#c7d2fe">404</div>
    <h2>Not found</h2>
    <p class="text-muted">The page or document does not exist, or you cannot access it.</p>
    <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house me-1"></i> Back to start</a>
</div>
@endsection
