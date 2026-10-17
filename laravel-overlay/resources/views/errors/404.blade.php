@extends('layouts.app')
@section('title', 'Not found')
@section('content')
<div class="text-center py-5"><h1 class="display-4">404</h1><h4>Not found</h4><p class="text-muted">The page or document you are looking for does not exist, or you cannot access it.</p>
<a class="btn btn-primary" href="{{ url('/') }}">Back to dashboard</a></div>
@endsection
