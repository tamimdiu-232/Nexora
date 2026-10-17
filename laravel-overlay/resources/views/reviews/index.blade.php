@extends('layouts.app')
@section('title', 'Review queue')
@section('content')
<div class="d-flex justify-content-between mb-3"><h3 class="mb-0">Review queue</h3>
    <div class="btn-group">@foreach(['pending','verified','rejected','needs_correction','all'] as $s)<a class="btn btn-sm btn-outline-secondary {{ $status === $s ? 'active' : '' }}" href="{{ route('reviews.index', ['status' => $s]) }}">{{ str_replace('_', ' ', ucfirst($s)) }}</a>@endforeach</div></div>
@forelse($reviews as $r)
<div class="card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between"><div class="small text-muted">Requested by {{ $r->requester->name }} - {{ $r->created_at->format('Y-m-d H:i') }}</div>@include('partials.status', ['status' => $r->status])</div>
    <p class="mb-1 mt-2"><strong>Question:</strong> {{ $r->message->question() }}</p>
    <p class="mb-2"><strong>AI answer:</strong> <span style="white-space:pre-wrap">{{ $r->message->content }}</span></p>
    @php $visible = $r->message->citations->filter(fn ($c) => $c->document && auth()->user()->can('view', $c->document)); @endphp
    @foreach($visible as $i => $c)
    <div class="citation p-2 mb-1 small">[{{ $i + 1 }}] {{ $c->document->title }}, page {{ $c->page_number }}
        - <a target="_blank" href="{{ route('documents.show', [$c->document, 'page' => $c->page_number]) }}#preview">open source document</a>
        <div class="text-muted">{{ $c->snippet }}</div></div>
    @endforeach
    @if($r->message->citations->count() > $visible->count())<div class="small text-warning">Some cited sources are outside your access; you cannot fully verify this answer.</div>@endif
    @if($r->status !== 'pending')<p class="small mt-2 mb-1">Decision by {{ $r->reviewer?->name }} on {{ $r->decided_at?->format('Y-m-d H:i') }}@if($r->comment): "{{ $r->comment }}"@endif</p>@endif
    @can('decide', $r)
    <form method="POST" action="{{ route('reviews.decide', $r) }}" class="row g-2 mt-2" data-loading="Saving...">@csrf
        <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="verified">Verified</option><option value="needs_correction">Needs correction</option><option value="rejected">Rejected</option></select></div>
        <div class="col-md-7"><input name="comment" class="form-control form-control-sm" placeholder="Comment (required unless verified)" maxlength="2000"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Record decision</button></div>
    </form>
    @endcan
    @if(isset($history[$r->id]))<details class="small mt-2"><summary>History</summary>
        @foreach($history[$r->id] as $h)<div>{{ $h->created_at->format('Y-m-d H:i') }} - {{ $h->action }} by {{ $h->user?->name }}@if($h->metadata) ({{ $h->metadata['from'] ?? '' }} → {{ $h->metadata['to'] ?? '' }})@endif</div>@endforeach</details>@endif
</div></div>
@empty<div class="text-muted">Nothing here.</div>@endforelse
{{ $reviews->links('pagination::bootstrap-5') }}
@endsection
