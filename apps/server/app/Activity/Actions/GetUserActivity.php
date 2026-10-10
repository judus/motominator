<?php

namespace App\Activity\Actions;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Collection;

class GetUserActivity
{
    /**
     * Pass the authenticated owner, never a user ID supplied by a client or an AI.
     * This bounded feed is a future context input; current domain records remain authoritative.
     *
     * @return Collection<int, UserActivity>
     */
    public function __invoke(User $owner, int $limit = 20): Collection
    {
        return UserActivity::query()->where('user_id', $owner->id)
            ->latest('id')->limit(max(1, min($limit, 100)))->get();
    }
}
