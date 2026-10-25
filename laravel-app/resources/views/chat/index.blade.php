@extends('layouts.app')
@section('title', 'Ask AI')
@section('content')
<x-page-header title="Ask AI" subtitle="Questions are answered only from documents you can access, with the source page for every claim." />

<div class="row g-3">
    <div class="col-lg-3">
        <form method="POST" action="{{ route('chat.store') }}" class="mb-3">@csrf
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg me-1"></i> New conversation</button></form>
        <div class="nx-card p-2">
            @forelse($sessions as $session)
                <a class="nx-session {{ $current?->id === $session->id ? 'active' : '' }}" href="{{ route('chat.index', ['session' => $session->id]) }}">
                    {{ \Illuminate\Support\Str::limit($session->title, 38) }}<small>{{ $session->updated_at->diffForHumans() }}</small></a>
            @empty
                <div class="text-muted small p-2">No conversations yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-9">
    @if(!$current)
        <div class="nx-card"><x-empty icon="bi-stars" title="Start a conversation">Create a new conversation, then ask about budgets, deadlines, decisions or anything in your documents.</x-empty></div>
    @else
        <div class="nx-card nx-chat">
            <div class="nx-card-head"><h2>{{ \Illuminate\Support\Str::limit($current->title, 70) }}</h2>
                <form method="POST" action="{{ route('chat.destroy', $current) }}" data-confirm="Delete this conversation?">@csrf @method('DELETE')
                    <button class="btn btn-ghost btn-sm text-danger" type="submit"><i class="bi bi-trash"></i></button></form></div>

            <div class="nx-thread">
                @forelse($current->messages as $message)
                    @if($message->role === 'user')
                        <div class="nx-msg user"><div class="who"><i class="bi bi-person-fill"></i></div><div class="bubble">{{ $message->content }}</div></div>
                    @else
                        @php $visible = $message->citations->filter(fn ($c) => $c->document && auth()->user()->can('view', $c->document)); @endphp
                        <div class="nx-msg ai"><div class="who"><i class="bi bi-stars"></i></div>
                            <div class="bubble">
                                <div class="nx-badges">
                                    <x-status :status="$message->status ?? 'answered'" />
                                    <span class="nx-pill nx-pill-medium">AI-generated, not verified</span>
                                    @if($message->generated_by)<span class="small text-muted">{{ $message->generated_by }}</span>@endif
                                    @if($message->review)<span class="small text-muted">· review:</span><x-status :status="$message->review->status" />@endif
                                </div>
                                {{ $message->content }}
                                @if($message->status === 'conflicting_evidence')
                                    <div class="alert alert-warning mt-2 mb-0 py-2 small"><i class="bi bi-exclamation-triangle-fill"></i><div>The sources disagree. Check the cited documents.</div></div>
                                @endif
                                @foreach($visible as $i => $citation)
                                    <div class="nx-citation"><b>[{{ $i + 1 }}]</b> <a href="{{ route('documents.show', $citation->document) }}">{{ $citation->document->title }}</a> · page {{ $citation->page_number }}
                                        · <a href="{{ route('documents.show', [$citation->document, 'page' => $citation->page_number]) }}#preview">open source</a>
                                        <div class="snippet">{{ $citation->snippet }}</div></div>
                                @endforeach
                                @if($message->citations->count() > $visible->count())<div class="small text-muted mt-2">Some sources are not available to you.</div>@endif
                                @if(auth()->user()->hasPermission('reviews.submit') && $message->status !== 'error' && (!$message->review || $message->review->status !== 'pending'))
                                    <form method="POST" action="{{ route('reviews.store', $message) }}" class="mt-3">@csrf
                                        <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-patch-check me-1"></i> Submit for human review</button></form>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="text-center text-muted my-auto">
                        <i class="bi bi-chat-square-text fs-1 d-block mb-2" style="color:#c7d2fe"></i>Ask your first question
                        <div class="nx-suggest">
                            <button type="button" data-fill="#question" data-text="What is the project budget and deadline?">Budget and deadline?</button>
                            <button type="button" data-fill="#question" data-text="এই project-এর budget কত এবং deadline কবে?">বাজেট ও সময়সীমা কত?</button>
                            <button type="button" data-fill="#question" data-text="When must progress reports be submitted?">Report submission date?</button>
                        </div>
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('chat.ask', $current) }}" class="nx-composer" data-loading="Searching your documents...">
                @csrf
                <div class="d-flex gap-2 align-items-end">
                    <textarea id="question" name="question" class="form-control" rows="2" maxlength="1000" required placeholder="Ask about your documents..."></textarea>
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-send me-1"></i> Ask</button>
                </div>
            </form>
        </div>
    @endif
    </div>
</div>
@endsection
