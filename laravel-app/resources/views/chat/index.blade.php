@extends('layouts.app')

@section('title', 'Ask AI')
@section('page_title', 'Grounded AI Intelligence & Q&A')

@section('content')
<div class="row g-4">
    <!-- Conversation Sessions Sidebar -->
    <div class="col-lg-3">
        <div class="nx-card h-100">
            <div class="nx-card-header d-flex justify-content-between align-items-center">
                <span>Conversations</span>
                <form method="POST" action="{{ route('chat.store') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary p-1 px-2" title="New Chat">
                        <i class="bi bi-plus-lg me-1"></i>New
                    </button>
                </form>
            </div>
            <div class="nx-card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    @forelse($sessions as $s)
                        <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 px-3 {{ $currentSession && $currentSession->id === $s->id ? 'active' : '' }}">
                            <a href="{{ route('chat.show', $s) }}" class="text-truncate text-decoration-none {{ $currentSession && $currentSession->id === $s->id ? 'text-white' : 'text-dark' }} flex-grow-1 small fw-medium">
                                <i class="bi bi-chat-left-text me-2"></i>{{ $s->title }}
                            </a>
                            <form method="POST" action="{{ route('chat.destroy', $s) }}" class="ms-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link btn-sm p-0 {{ $currentSession && $currentSession->id === $s->id ? 'text-white-50' : 'text-muted' }}" data-confirm="Delete this conversation?">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted small">No conversations yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Active Chat Area -->
    <div class="col-lg-9">
        <div class="nx-card d-flex flex-column" style="min-height: 600px;">
            <div class="nx-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-primary fs-5"></i>
                    <span class="fw-bold">{{ $currentSession ? $currentSession->title : 'Ask Nexora AI' }}</span>
                </div>
                <span class="badge bg-light text-muted border">Extractive Grounded RAG</span>
            </div>

            <!-- Messages Stream -->
            <div class="nx-chat-box flex-grow-1">
                @if(!$currentSession || $messages->isEmpty())
                    <div class="text-center py-5 my-auto">
                        <div class="mb-3">
                            <i class="bi bi-stars display-4 text-primary opacity-50"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Grounded Document Q&A</h5>
                        <p class="text-muted small max-w-md mx-auto mb-4">
                            Ask questions against verified institutional records. Every response includes verifiable document and page citations, and flags conflicting evidence.
                        </p>

                        <!-- Example Question Chips -->
                        <div class="text-muted small fw-semibold mb-2">Example questions to test:</div>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 example-chip" data-question="এই project-এর budget কত এবং deadline কবে?">
                                এই project-এর budget কত এবং deadline কবে?
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 example-chip" data-question="এই প্রকল্পের বাজেট কত এবং সময়সীমা কবে?">
                                এই প্রকল্পের বাজেট কত এবং সময়সীমা কবে?
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 example-chip" data-question="What is the project budget and submission deadline?">
                                What is the project budget and submission deadline?
                            </button>
                        </div>
                    </div>
                @else
                    @foreach($messages as $msg)
                        @if($msg->role === 'user')
                            <!-- User Message -->
                            <div class="nx-chat-bubble nx-chat-user">
                                <div class="fw-medium">{{ $msg->content }}</div>
                                <div class="text-white-50 text-end mt-1" style="font-size: 0.7rem;">
                                    {{ $msg->created_at->format('H:i') }}
                                </div>
                            </div>
                        @else
                            <!-- Assistant Message -->
                            <div class="nx-chat-bubble nx-chat-assistant w-100">
                                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                    <x-status :status="$msg->status" />
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.7rem;">
                                        AI-generated, not verified
                                    </span>
                                    <span class="text-muted small ms-auto" style="font-size: 0.72rem;">
                                        Generated by: {{ $msg->generated_by }}
                                    </span>
                                </div>

                                <!-- Conflicting Evidence Warning -->
                                @if($msg->status === 'conflicting_evidence')
                                    <div class="alert alert-warning border d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                                        <div>
                                            <strong>Warning: Conflicting Sources Detected.</strong> Source documents specify differing figures or timelines (e.g. initial proposal vs. revised cost estimates).
                                        </div>
                                    </div>
                                @endif

                                <!-- Message Body -->
                                <div class="text-dark mb-3" style="line-height: 1.7;">
                                    {{ $msg->content }}
                                </div>

                                <!-- Citations Block -->
                                @if($msg->citations->isNotEmpty())
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="small fw-bold text-secondary mb-2">Source Document & Page Citations:</div>
                                        <div class="d-flex flex-column gap-2">
                                            @foreach($msg->citations as $cite)
                                                <div class="nx-citation-card">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <div>
                                                            <span class="badge bg-primary text-white me-1">[{{ $loop->iteration }}]</span>
                                                            <strong class="text-dark">{{ $cite->document?->title ?? 'Authorized Document' }}</strong>
                                                            <span class="text-muted ms-1">(Page {{ $cite->page_number }})</span>
                                                        </div>
                                                        @if($cite->document)
                                                            <a href="{{ route('documents.preview', $cite->document) }}#page={{ $cite->page_number }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                                <i class="bi bi-box-arrow-up-right me-1"></i>Open Page {{ $cite->page_number }}
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <div class="text-secondary small mt-1 font-monospace" style="font-size: 0.8rem;">
                                                        "{{ $cite->snippet }}"
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Human Review Workflow Action -->
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                    <div class="small text-muted">
                                        @if($msg->review)
                                            <span class="badge bg-light text-secondary border">
                                                Review Status: {{ ucfirst($msg->review->status) }}
                                            </span>
                                            @if($msg->review->status === 'verified')
                                                <span class="text-success ms-1 small"><i class="bi bi-patch-check-fill"></i> Verified by Human Reviewer</span>
                                            @endif
                                        @else
                                            <span class="text-muted" style="font-size: 0.75rem;">Pending human verification</span>
                                        @endif
                                    </div>

                                    @if(!$msg->review && Auth::user()->hasPermission('reviews.submit'))
                                        <form method="POST" action="{{ route('reviews.submit', $msg) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-patch-question me-1"></i>Submit for Human Review
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <!-- Question Input Footer -->
            <div class="p-3 border-top bg-light">
                @php
                    $actionUrl = $currentSession ? route('chat.ask', $currentSession) : route('chat.store');
                @endphp
                <form method="POST" action="{{ $actionUrl }}" id="askForm" data-loading>
                    @csrf
                    <div class="input-group">
                        <textarea name="question" id="questionInput" class="form-control" rows="2" placeholder="Ask a question in English, Bangla, or mixed (e.g. এই project-এর budget কত এবং deadline কবে?)..." required></textarea>
                        <button type="submit" class="btn btn-primary px-4 fw-medium d-flex align-items-center">
                            <i class="bi bi-send-fill me-2"></i>Ask
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.example-chip').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = document.getElementById('questionInput');
        if (input) {
            input.value = this.getAttribute('data-question');
            input.focus();
        }
    });
});
</script>
@endpush
