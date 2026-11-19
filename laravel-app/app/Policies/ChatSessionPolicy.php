<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;

class ChatSessionPolicy
{
    /**
     * Chat sessions are strictly private to their owner.
     * Even Organization Admins and Super Admins cannot view another user's chat.
     */
    public function view(User $user, ChatSession $session): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return (int) $user->id === (int) $session->user_id;
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $user->hasPermission('chat.use');
    }

    public function delete(User $user, ChatSession $session): bool
    {
        return (int) $user->id === (int) $session->user_id;
    }
}
