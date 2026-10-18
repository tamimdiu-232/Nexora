<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $u): bool { return $u->hasPermission('users.manage'); }
    public function create(User $u): bool { return $u->hasPermission('users.manage'); }

    /** Account recovery (reset link, revoke sessions, deactivate) - platform security staff only, never on yourself. */
    public function recover(User $actor, User $target): bool
    {
        return $actor->hasPermission('security.manage') && $actor->id !== $target->id;
    }

    public function update(User $actor, User $target): bool
    {
        if (!$actor->hasPermission('users.manage') || $actor->id === $target->id) return false; // no self-edit of role/status
        return User::manageableBy($actor)->whereKey($target->id)->exists();
    }
}
