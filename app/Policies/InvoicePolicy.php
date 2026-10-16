<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Invoices belong to exactly one user; only that user may see them.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id;
    }

    /**
     * Cancelled invoices and cancellation invoices are final.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id && ! $invoice->isLocked();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id && ! $invoice->isLocked();
    }

    /**
     * A cancelled invoice stays as long as its cancellation exists, so the numbering has no gaps.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id && ! $invoice->isCancelled();
    }
}
