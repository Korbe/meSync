<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function view(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    /**
     * Only drafts can be changed; a sent offer is revised as a copy.
     */
    public function update(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id && ! $offer->isLocked();
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }
}
