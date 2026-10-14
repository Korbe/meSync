<?php

namespace App\Policies;

use App\Models\MaintenanceContract;
use App\Models\User;

class MaintenanceContractPolicy
{
    /**
     * Contracts belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, MaintenanceContract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    public function delete(User $user, MaintenanceContract $contract): bool
    {
        return $contract->user_id === $user->id;
    }
}
