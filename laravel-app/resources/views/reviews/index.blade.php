@extends('layouts.app')
@section('title', 'Review queue')
@section('content')
<x-page-header title="Review queue" subtitle="Check AI answers against their sources. Nothing is verified automatically.">
    <div class="btn-group btn-group-sm">
        @foreach(['pending', 'verified', 'rejected', 'needs_correction', 'all'] as $filter)
            <a class="btn {{ $status === $filter ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('reviews.index', ['status' => $filter]) }}">{{ ucfirst(str_replace('_', ' ', $filter)) }}</a>
        @endforeach
    </div>
</x-page-header>

@forelse($reviews as $review)
    @php $visible = $review->message->citations->filter(fn ($c) => $c->document && auth()->user()->can('view', $c->document)); @endphp
    <div class="nx-card mb-3">
        <div class="nx-card-head">
            <div class="small text-muted">Requested by <b class="text-body">{{ $review->requester->name }}</b> · {{ $review->created_at->format('d M Y, H:i') }}</div>
            <x-status :status="$review->status" />
        </div>
        <div class="nx-card-body">
            <div class="small text-muted text-uppercase mb-1">Question</div>
            <p class="fw-medium">{{ $review->message->question() }}</p>
            <div class="small text-muted text-uppercase mb-1">AI answer</div>
            <p style="white-space: pre-wrap">{{ $review->message->content }}</p>
            @foreach($visible as $i => $citation)
                <div class="nx-citation"><b>[{{ $i + 1 }}]</b> {{ $citation->document->title }} · page {{ $citation->page_number }}
                    · <a target="_blank" href="{{ route('documents.show', [$citation->document, 'page' => $citation->page_number]) }}#preview">open source document</a>
                    <div class="snippet">{{ $citation->snippet }}</div></div>
            @endforeach
            @if($review->message->citations->count() > $visible->count())<div class="small text-warning mt-2">Some cited sources are outside your access, so you cannot fully verify this answer.</div>@endif

            @if($review->status !== 'pending')
                <div class="small mt-3 text-muted">Decision by <b class="text-body">{{ $review->reviewer?->name }}</b> on {{ $review->decided_at?->format('d M Y, H:i') }}@if($review->comment): “{{ $review->comment }}”@endif</div>
            @endif
            @can('decide', $review)
                <form method="POST" action="{{ route('reviews.decide', $review) }}" class="row g-2 mt-3" data-loading="Saving...">@csrf
                    <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="verified">Verified</option><option value="needs_correction">Needs correction</option><option value="rejected">Rejected</option></select></div>
                    <div class="col-md-7"><input name="comment" class="form-control form-control-sm" maxlength="2000" placeholder="Comment (required unless verified)"></div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Record decision</button></div>
                </form>
            @endcan
            @if(isset($history[$review->id]))
                <details class="small mt-3"><summary class="text-muted">History</summary>
                    @foreach($history[$review->id] as $entry)<div class="mt-1">{{ $entry->created_at->format('d M Y H:i') }} · {{ $entry->action }} · {{ $entry->user?->name }}@if($entry->metadata) ({{ $entry->metadata['from'] ?? '' }} → {{ $entry->metadata['to'] ?? '' }})@endif</div>@endforeach
                </details>
            @endif
        </div>
    </div>
@empty
    <div class="nx-card"><x-empty icon="bi-patch-check" title="Nothing to review">Answers submitted for review will appear here.</x-empty></div>
@endforelse
{{ $reviews->links('pagination::bootstrap-5') }}
@endsection
