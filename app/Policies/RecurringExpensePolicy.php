<?php

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    /**
     * Contracts belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, RecurringExpense $expense): bool
    {
        return $expense->user_id === $user->id;
    }

    public function delete(User $user, RecurringExpense $expense): bool
    {
        return $expense->user_id === $user->id;
    }
}
