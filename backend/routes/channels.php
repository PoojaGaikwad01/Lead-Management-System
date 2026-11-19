<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admins', fn (User $user) => $user->hasAdminAccess());
Broadcast::channel('users.{id}', fn (User $user, int $id) => $user->id === $id);
Broadcast::channel('branches.{id}', fn (User $user, int $id) => $user->branch_id === $id);
