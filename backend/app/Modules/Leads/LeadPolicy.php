<?php

namespace App\Modules\Leads;

use App\Models\User;
use App\Modules\Leads\Models\Lead;

class LeadPolicy
{
    public function view(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function reply(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function markReplySent(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->hasAdminAccess();
    }

    public function remind(User $user, Lead $lead): bool
    {
        return $user->hasAdminAccess();
    }
}
