<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        if (!$user->hasPermission('users.manage')) {
            return false;
        }

        // Nobody can edit their own role or active status
        if ((int) $user->id === (int) $target->id) {
            return false;
        }

        // Super Admin can manage Organization Admins only
        if ($user->isSuperAdmin()) {
            return $target->isOrgAdmin();
        }

        // Org Admin can manage any role in their own organization except Super Admin
        if ($user->isOrgAdmin()) {
            return (int) $user->organization_id === (int) $target->organization_id && !$target->isSuperAdmin();
        }

        // Dept Admin can manage officer, reviewer, viewer in their own department
        if ($user->isDeptAdmin()) {
            return (int) $user->organization_id === (int) $target->organization_id
                && (int) $user->department_id === (int) $target->department_id
                && in_array($target->role, ['officer', 'reviewer', 'viewer'], true);
        }

        return false;
    }

    public function delete(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }

    /**
     * Account recovery actions (reset link, revoke sessions, deactivation).
     * Platform Super Admin only; never on your own account.
     */
    public function recover(User $user, User $target): bool
    {
        if (!$user->isSuperAdmin()) {
            return false;
        }

        // Never on yourself
        return (int) $user->id !== (int) $target->id;
    }
}
