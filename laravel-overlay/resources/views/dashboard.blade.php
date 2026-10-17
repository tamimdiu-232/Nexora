@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h3 class="mb-3">Dashboard</h3>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Accessible documents</div><div class="fs-2">{{ $total }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Processed</div><div class="fs-2 text-success">{{ $byStatus['completed'] ?? 0 }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">In queue</div><div class="fs-2">{{ ($byStatus['pending'] ?? 0) + ($byStatus['processing'] ?? 0) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted">Failed</div><div class="fs-2 text-danger">{{ $byStatus['failed'] ?? 0 }}</div></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header">Documents by category</div>
            <ul class="list-group list-group-flush">
                @forelse($byCategory as $cat => $n)<li class="list-group-item d-flex justify-content-between">{{ $cat }}<span class="badge bg-primary">{{ $n }}</span></li>
                @empty<li class="list-group-item text-muted">No documents yet.</li>@endforelse
            </ul></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header">Failed processing</div>
            <ul class="list-group list-group-flush">
                @forelse($failed as $d)<li class="list-group-item"><a href="{{ route('documents.show', $d) }}">{{ $d->title }}</a><div class="small text-danger">{{ $d->processing_error }}</div></li>
                @empty<li class="list-group-item text-muted">No failed jobs.</li>@endforelse
            </ul>
            @if(auth()->user()->hasPermission('reviews.view'))
            <div class="card-footer">Reviews:
                @foreach(['pending','verified','rejected','needs_correction'] as $s)@include('partials.status', ['status' => $s]) {{ $reviewCounts[$s] ?? 0 }} @endforeach
            </div>@endif
            @if($aiHealth)<div class="card-footer small">AI service: <strong class="{{ ($aiHealth['status'] ?? '') === 'ok' ? 'text-success' : 'text-danger' }}">{{ $aiHealth['status'] ?? 'unknown' }}</strong>
                @if(isset($aiHealth['documents_indexed'])) - {{ $aiHealth['documents_indexed'] }} documents indexed @endif</div>@endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header">Recent activity</div>
            <ul class="list-group list-group-flush">
                @forelse($activity as $a)<li class="list-group-item small"><strong>{{ $a->action }}</strong> <span class="text-muted">{{ $a->user?->name ?? 'anonymous' }} - {{ $a->created_at?->diffForHumans() }}</span></li>
                @empty<li class="list-group-item text-muted">No activity yet.</li>@endforelse
            </ul></div>
    </div>
</div>
@endsection
