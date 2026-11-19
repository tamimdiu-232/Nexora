<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $user->hasPermission('reviews.view') || $user->hasPermission('reviews.decide');
    }

    public function view(User $user, Review $review): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        // Must be in same organization
        if ((int) $user->organization_id !== (int) $review->message->session->organization_id) {
            return false;
        }

        return $user->hasPermission('reviews.view') || $user->hasPermission('reviews.decide');
    }

    public function submit(User $user, ChatMessage $message): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (!$user->hasPermission('reviews.submit')) {
            return false;
        }

        // Only owner of the conversation can submit their message for review
        return (int) $user->id === (int) $message->session->user_id;
    }

    public function decide(User $user, Review $review): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (!$user->hasPermission('reviews.decide')) {
            return false;
        }

        // Must be in same organization
        if ((int) $user->organization_id !== (int) $review->message->session->organization_id) {
            return false;
        }

        // CRITICAL: A reviewer can NEVER decide their own submission
        if ((int) $user->id === (int) $review->requested_by) {
            return false;
        }

        // Can only decide pending reviews
        return $review->status === 'pending';
    }
}
