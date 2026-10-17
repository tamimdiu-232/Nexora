@extends('layouts.app')
@section('title', 'Session expired')
@section('content')
<div class="text-center py-5"><h1 class="display-4">419</h1><h4>Session expired</h4><p class="text-muted">Your session expired. Reload the page and try again.</p>
<a class="btn btn-primary" href="{{ url('/') }}">Back to dashboard</a></div>
@endsection
