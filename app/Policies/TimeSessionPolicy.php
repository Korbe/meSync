<?php

namespace App\Policies;

use App\Models\TimeSession;
use App\Models\User;

class TimeSessionPolicy
{
    /**
     * Stopwatch runs belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, TimeSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function delete(User $user, TimeSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
