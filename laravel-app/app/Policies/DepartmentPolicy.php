<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('departments.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        if (!$user->hasPermission('departments.manage')) {
            return false;
        }

        return (int) $user->organization_id === (int) $department->organization_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}
