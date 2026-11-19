<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Branches\Branch;
use App\Modules\Branches\BranchPolicy;
use App\Modules\Leads\LeadPolicy;
use App\Modules\Leads\Models\Lead;
use App\Modules\Users\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);

        Gate::define('manage-settings', fn (User $user) => $user->isSuperAdmin());
        Gate::define('view-reports', fn (User $user) => $user->hasAdminAccess());
        Gate::define('manage-imports', fn (User $user) => $user->hasAdminAccess());
    }
}
