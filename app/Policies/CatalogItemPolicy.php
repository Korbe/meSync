<?php

namespace App\Policies;

use App\Models\CatalogItem;
use App\Models\User;

class CatalogItemPolicy
{
    /**
     * Catalog items belong to exactly one user; only that user may touch them.
     */
    public function update(User $user, CatalogItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function delete(User $user, CatalogItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
