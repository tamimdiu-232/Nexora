@extends('layouts.guest')
@section('title', Too many requests)
@section('content')
<div class="text-center">
    <div class="display-3 fw-bold" style="color:#c7d2fe">429</div>
    <h2>Too many requests</h2>
    <p class="text-muted">Please slow down and try again shortly.</p>
    <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house me-1"></i> Back to start</a>
</div>
@endsection
