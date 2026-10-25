<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;

/** Chat history is private to its owner - not even admins can read it. */
class ChatSessionPolicy
{
    public function view(User $u, ChatSession $s): bool { return $u->hasPermission('chat.use') && $s->user_id === $u->id; }
    public function ask(User $u, ChatSession $s): bool { return $this->view($u, $s); }
    public function delete(User $u, ChatSession $s): bool { return $this->view($u, $s); }
}
