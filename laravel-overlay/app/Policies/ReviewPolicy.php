<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    private function sameScope(User $u, Review $r): bool
    {
        return $u->isSuper() || ($u->organization_id !== null && $r->requester->organization_id === $u->organization_id);
    }

    public function view(User $u, Review $r): bool { return $u->hasPermission('reviews.view') && $this->sameScope($u, $r); }

    /** Reviewers cannot decide their own submissions. */
    public function decide(User $u, Review $r): bool
    {
        return $u->hasPermission('reviews.decide') && $this->sameScope($u, $r) && $r->requested_by !== $u->id;
    }
}
