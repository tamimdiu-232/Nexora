@extends('layouts.app')

@section('title', 'Human Verification Reviews')
@section('page_title', 'AI Answer Human Verification')

@section('content')
<x-page-header title="Human Review Queue" subtitle="Audit and verify AI-generated answers against institutional source evidence" />

<div class="nx-card mb-4">
    <div class="nx-card-header d-flex justify-content-between align-items-center">
        <!-- Status Filter Tabs -->
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('reviews.index', ['status' => 'pending']) }}" class="btn {{ $status === 'pending' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Pending (Needs Decision)
            </a>
            <a href="{{ route('reviews.index', ['status' => 'verified']) }}" class="btn {{ $status === 'verified' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Verified
            </a>
            <a href="{{ route('reviews.index', ['status' => 'needs_correction']) }}" class="btn {{ $status === 'needs_correction' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Needs Correction
            </a>
            <a href="{{ route('reviews.index', ['status' => 'rejected']) }}" class="btn {{ $status === 'rejected' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Rejected
            </a>
            <a href="{{ route('reviews.index', ['status' => 'all']) }}" class="btn {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                All Reviews
            </a>
        </div>
    </div>

    <div class="nx-card-body p-0">
        <div class="table-responsive">
            <table class="nx-table">
                <thead>
                    <tr>
                        <th>Submitted Answer & Context</th>
                        <th>Requester</th>
                        <th>Status</th>
                        <th>Decided By</th>
                        <th class="text-end">Verification Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $rev)
                        @php
                            $msg = $rev->message;
                            $isOwnSubmission = ((int) Auth::id() === (int) $rev->requested_by);
                            $canDecide = Auth::user()->hasPermission('reviews.decide') && !$isOwnSubmission && $rev->status === 'pending';
                        @endphp
                        <tr>
                            <td style="max-width: 440px;">
                                <div class="small fw-semibold text-dark mb-1">
                                    Conversation: {{ $msg->session->title ?? 'Direct Question' }}
                                </div>
                                <div class="small text-secondary bg-light p-2 rounded-2 mb-2 font-monospace" style="font-size: 0.8rem;">
                                    {{ Str::limit($msg->content, 180) }}
                                </div>

                                <!-- Citations badge -->
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge bg-light text-muted border">
                                        {{ $msg->citations->count() }} Source Citation(s)
                                    </span>
                                    @if($rev->comment)
                                        <div class="small text-muted ms-2 fst-italic">Note: {{ $rev->comment }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium small">{{ $rev->requester?->name }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $rev->created_at->format('M d, H:i') }}</div>
                            </td>
                            <td>
                                <x-status :status="$rev->status" />
                            </td>
                            <td class="small text-muted">
                                @if($rev->reviewer)
                                    <div>{{ $rev->reviewer->name }}</div>
                                    <div style="font-size: 0.72rem;">{{ $rev->decided_at?->format('M d, H:i') }}</div>
                                @else
                                    <span class="fst-italic text-muted">Awaiting decision</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($canDecide)
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#decisionModal{{ $rev->id }}">
                                        <i class="bi bi-shield-check me-1"></i>Verify / Decide
                                    </button>
                                @elseif($isOwnSubmission)
                                    <span class="badge bg-secondary-subtle text-secondary" title="Reviewers cannot decide their own submissions">
                                        Own Submission
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">
                                        {{ ucfirst($rev->status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>

                        <!-- Decision Modal -->
                        @if($canDecide)
                            <div class="modal fade" id="decisionModal{{ $rev->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('reviews.decide', $rev) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Human Review Decision #{{ $rev->id }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-secondary">AI Assistant Generated Answer:</label>
                                                    <div class="p-3 bg-light rounded-2 small text-dark" style="line-height: 1.6;">
                                                        {{ $msg->content }}
                                                    </div>
                                                </div>

                                                <!-- Citations Inspection -->
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-secondary">Cited Source Documents & Pages:</label>
                                                    <div class="d-flex flex-column gap-2">
                                                        @foreach($msg->citations as $cite)
                                                            <div class="p-2 border rounded-2 bg-white small d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <strong>{{ $cite->document?->title ?? 'Authorized Document' }}</strong>
                                                                    <span class="text-muted ms-1">(Page {{ $cite->page_number }})</span>
                                                                    <div class="small text-muted font-monospace mt-1">"{{ $cite->snippet }}"</div>
                                                                </div>
                                                                @if($cite->document)
                                                                    <a href="{{ route('documents.preview', $cite->document) }}#page={{ $cite->page_number }}" target="_blank" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                                                        Open Page {{ $cite->page_number }}
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-secondary">Review Decision</label>
                                                    <select name="status" class="form-select" id="decisionSelect{{ $rev->id }}" required>
                                                        <option value="verified">Verified (Confirmed against source documents)</option>
                                                        <option value="needs_correction">Needs Correction (Partially inaccurate or missing details)</option>
                                                        <option value="rejected">Rejected (Unsupported or inaccurate answer)</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-secondary">Reviewer Comment / Correction Guidance</label>
                                                    <textarea name="comment" class="form-control" rows="3" placeholder="Required if rejecting or requesting correction. Optional if verified."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Submit Final Decision</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                No reviews found in this status category.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($reviews->hasPages())
        <div class="p-3 border-top">
            {{ $reviews->links() }}
        </div>
    @endif
</div>
@endsection
