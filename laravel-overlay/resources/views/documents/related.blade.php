@extends('layouts.app')
@section('title', 'Related documents')
@section('content')
<h3>Related to: <a href="{{ route('documents.show', $document) }}">{{ $document->title }}</a></h3>
<p class="text-muted">Based on semantic similarity and shared terms, limited to documents you are allowed to see. Similarity is a heuristic, not proof of a relationship.</p>
@if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
@if($document->processing_status !== 'completed')<div class="alert alert-info">This document is not processed yet.</div>@endif
@forelse($items as $r)
<div class="card mb-2"><div class="card-body">
    <a href="{{ route('documents.show', $r['doc']) }}" class="fs-5">{{ $r['doc']->title }}</a>
    <span class="badge bg-light text-dark border">{{ $r['doc']->category }}</span> <span class="badge bg-secondary">similarity {{ $r['score'] }}</span>
    @if(!empty($r['explanation']))<div class="small text-muted">{{ $r['explanation'] }}</div>@endif
    <div class="small mt-1">{{ $r['snippet'] ?? '' }}</div>
</div></div>
@empty
@if(!$error && $document->processing_status === 'completed')<div class="text-muted">No related documents found.</div>@endif
@endforelse
@endsection
