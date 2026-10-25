<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $status = $request->input('status', 'pending');
        $reviews = Review::with(['message.citations.document', 'requester', 'reviewer'])
            ->whereHas('requester', fn ($q) => $q->where('organization_id', $u->organization_id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()->paginate(10)->withQueryString();

        $history = AuditLog::with('user')->where('subject_type', Review::class)
            ->whereIn('subject_id', $reviews->pluck('id'))->orderBy('id')->get()->groupBy('subject_id');

        return view('reviews.index', ['reviews' => $reviews, 'history' => $history, 'status' => $status]);
    }

    /** An answer is NEVER verified automatically: it only enters the queue as "pending". */
    public function store(Request $request, ChatMessage $message)
    {
        $u = $request->user();
        abort_unless($message->role === 'assistant', 422);
        Gate::authorize('ask', $message->session); // only the owner of the conversation may submit it
        if (Review::where('chat_message_id', $message->id)->where('status', 'pending')->exists()) {
            return back()->with('success', 'Already waiting for review.');
        }
        $review = Review::create(['chat_message_id' => $message->id, 'requested_by' => $u->id, 'status' => 'pending']);
        AuditLogger::log('review.requested', $review);
        return back()->with('success', 'Submitted for human review.');
    }

    public function decide(Request $request, Review $review)
    {
        Gate::authorize('decide', $review);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'needs_correction'])],
            'comment' => ['nullable', 'string', 'max:2000', 'required_unless:status,verified'],
        ]);
        $old = $review->status;
        $review->update(['status' => $data['status'], 'comment' => $data['comment'] ?? null,
            'reviewer_id' => $request->user()->id, 'decided_at' => now()]);
        AuditLogger::log('review.decided', $review, ['from' => $old, 'to' => $data['status']]);
        return back()->with('success', 'Review decision recorded.');
    }
}
