<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    /**
     * Inventory items belong to exactly one user; only that user may see or touch them.
     */
    public function view(User $user, InventoryItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
