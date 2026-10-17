@extends('layouts.app')
@section('title', 'Access denied')
@section('content')
<div class="text-center py-5"><h1 class="display-4">403</h1><h4>Access denied</h4><p class="text-muted">You do not have permission to view this page or document.</p>
<a class="btn btn-primary" href="{{ url('/') }}">Back to dashboard</a></div>
@endsection
