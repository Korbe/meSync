<?php

namespace App\Policies;

use App\Models\FinanceEntry;
use App\Models\User;

class FinanceEntryPolicy
{
    /**
     * Bookings belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, FinanceEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }

    public function delete(User $user, FinanceEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
