<?php

namespace App\Modules\Branches;

use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }
}
