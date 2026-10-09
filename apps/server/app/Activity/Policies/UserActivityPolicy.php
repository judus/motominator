<?php

namespace App\Activity\Policies;

use App\Models\User;
use App\Models\UserActivity;

class UserActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function view(User $user, UserActivity $activity): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, UserActivity $activity): bool
    {
        return false;
    }

    public function delete(User $user, UserActivity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
