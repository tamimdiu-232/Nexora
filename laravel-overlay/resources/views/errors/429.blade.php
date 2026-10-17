@extends('layouts.app')
@section('title', 'Too many requests')
@section('content')
<div class="text-center py-5"><h1 class="display-4">429</h1><h4>Too many requests</h4><p class="text-muted">Please slow down and try again shortly.</p>
<a class="btn btn-primary" href="{{ url('/') }}">Back to dashboard</a></div>
@endsection
