<?php

namespace App\Policies;

use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $u): bool { return $u->isSuper(); }
    public function create(User $u): bool { return $u->isSuper(); }
    public function update(User $u): bool { return $u->isSuper(); }
}
