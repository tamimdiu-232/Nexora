<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            abort(403, 'Platform administrators do not have access to organizational reviews.');
        }

        Gate::authorize('viewAny', Review::class);

        $status = $request->input('status', 'pending');

        $query = Review::whereHas('message.session', function ($q) use ($user) {
            $q->where('organization_id', $user->organization_id);
        })->with(['message.session.user', 'requester', 'reviewer', 'message.citations.document']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $reviews = $query->latest()->paginate(15)->withQueryString();

        return view('reviews.index', compact('reviews', 'status'));
    }

    public function submit(Request $request, ChatMessage $message)
    {
        $user = Auth::user();
        Gate::authorize('submit', [Review::class, $message]);

        // Check if message is already submitted
        if ($message->review) {
            return back()->with('status', 'This answer is already submitted for review.');
        }

        $review = Review::create([
            'chat_message_id' => $message->id,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        AuditLogger::log('review.submitted', $review, [
            'chat_message_id' => $message->id,
            'chat_session_id' => $message->chat_session_id,
        ]);

        return back()->with('status', 'Answer submitted for human reviewer verification.');
    }

    public function decide(Request $request, Review $review)
    {
        $user = Auth::user();
        Gate::authorize('decide', $review);

        $validated = $request->validate([
            'status' => ['required', 'in:verified,needs_correction,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Comment required unless verified
        if ($validated['status'] !== 'verified' && empty($validated['comment'])) {
            return back()->withErrors(['comment' => 'A comment explaining the decision is required when rejecting or requesting correction.']);
        }

        $review->update([
            'status' => $validated['status'],
            'reviewer_id' => $user->id,
            'comment' => $validated['comment'] ?? null,
            'decided_at' => now(),
        ]);

        AuditLogger::log('review.decided', $review, [
            'status' => $review->status,
            'reviewer_id' => $user->id,
        ]);

        return redirect()->route('reviews.index')->with('status', "Review marked as {$review->status}.");
    }
}
