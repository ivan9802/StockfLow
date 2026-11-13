<?php

namespace App\Events;

use App\Models\StockMovement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après chaque mouvement de stock, une fois la transaction validée.
 * Ne sert qu'aux alertes de seuil : si la transaction est annulée,
 * l'événement n'est jamais livré.
 */
class StockLevelChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly StockMovement $movement) {}
}
