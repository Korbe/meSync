<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    public function update(User $user, Monitor $monitor): bool
    {
        return $monitor->user_id === $user->id;
    }

    public function delete(User $user, Monitor $monitor): bool
    {
        return $monitor->user_id === $user->id;
    }
}
