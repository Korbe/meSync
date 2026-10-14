<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Customers belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $customer->user_id === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $customer->user_id === $user->id;
    }
}
