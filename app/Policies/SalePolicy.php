<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/** Le vendeur ne voit que ses propres ventes ; l'administrateur et le gestionnaire les voient toutes. */
class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sales.view-all') || $sale->user_id === $user->id;
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->can('sales.cancel') && ! $sale->isCancelled();
    }
}
