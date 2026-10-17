@extends('layouts.app')
@section('title', 'Ask AI')
@section('content')
<div class="row g-3">
    <div class="col-lg-3">
        <form method="POST" action="{{ route('chat.store') }}" class="mb-2">@csrf<button class="btn btn-primary w-100" type="submit">New conversation</button></form>
        <div class="list-group">
            @forelse($sessions as $s)
            <a class="list-group-item list-group-item-action {{ $current?->id === $s->id ? 'active' : '' }}" href="{{ route('chat.index', ['session' => $s->id]) }}">{{ $s->title }}<div class="small opacity-75">{{ $s->updated_at->diffForHumans() }}</div></a>
            @empty<div class="text-muted small">No conversations yet.</div>@endforelse
        </div>
    </div>
    <div class="col-lg-9">
    @if(!$current)
        <div class="card card-body text-muted">Start a new conversation, then ask a question about documents you are authorized to access, e.g. <em>এই project-এর budget কত এবং deadline কবে?</em></div>
    @else
        <div class="d-flex justify-content-between mb-2"><h5 class="mb-0">{{ $current->title }}</h5>
            <form method="POST" action="{{ route('chat.destroy', $current) }}" data-confirm="Delete this conversation?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></div>
        @foreach($current->messages as $m)
            @if($m->role === 'user')
            <div class="card msg-user mb-2"><div class="card-body py-2"><div class="small text-muted">You</div>{{ $m->content }}</div></div>
            @else
            <div class="card msg-ai mb-3"><div class="card-body">
                <div class="small text-muted mb-1">AI assistant @include('partials.status', ['status' => $m->status ?? 'answered'])
                    <span class="badge bg-warning text-dark">AI-generated, not verified</span>@if($m->generated_by)<span class="badge bg-light text-dark border">{{ $m->generated_by }}</span>@endif
                    @if($m->review) @include('partials.status', ['status' => $m->review->status]) review @endif</div>
                <div style="white-space:pre-wrap">{{ $m->content }}</div>
                @if($m->status === 'conflicting_evidence')<div class="alert alert-warning mt-2 py-1 small mb-0">Sources contain conflicting values. Please check the cited documents.</div>@endif
                @php $visible = $m->citations->filter(fn ($c) => $c->document && auth()->user()->can('view', $c->document)); @endphp
                @foreach($visible as $i => $c)
                    <div class="citation p-2 mt-2 small"><strong>[{{ $i + 1 }}]</strong>
                        <a href="{{ route('documents.show', $c->document) }}">{{ $c->document->title }}</a>, page {{ $c->page_number }}
                        - <a href="{{ route('documents.show', [$c->document, 'page' => $c->page_number]) }}#preview">open source</a>
                        <div class="text-muted">{{ $c->snippet }}</div></div>
                @endforeach
                @if($m->citations->count() > $visible->count())<div class="small text-muted mt-1">Some sources are not available to you.</div>@endif
                @if(auth()->user()->hasPermission('reviews.submit') && $m->status !== 'error' && (!$m->review || $m->review->status !== 'pending'))
                <form method="POST" action="{{ route('reviews.store', $m) }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Submit for human review</button></form>@endif
            </div></div>
            @endif
        @endforeach
        <form method="POST" action="{{ route('chat.ask', $current) }}" data-loading="Searching documents and answering..." class="card card-body">
            @csrf
            <label class="form-label">Your question</label>
            <textarea name="question" class="form-control" rows="2" maxlength="1000" required placeholder="Ask about your documents..."></textarea>
            <div class="mt-2"><button class="btn btn-primary" type="submit">Ask</button>
                <span class="text-muted small ms-2">Answers use only documents you are allowed to access.</span></div>
        </form>
    @endif
    </div>
</div>
@endsection
