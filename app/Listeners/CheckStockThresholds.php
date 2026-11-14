<?php

namespace App\Listeners;

use App\Events\StockLevelChanged;
use App\Services\AlertService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Évalue les seuils après chaque mouvement, une fois la transaction validée.
 * Un échec ici ne doit pas faire croire que l'opération de stock a échoué :
 * il est journalisé, et la liste des produits sous seuil reste calculée en direct.
 */
class CheckStockThresholds
{
    public function __construct(private readonly AlertService $alerts) {}

    public function handle(StockLevelChanged $event): void
    {
        try {
            $this->alerts->evaluate($event->movement);
        } catch (Throwable $e) {
            Log::critical('Évaluation des seuils impossible après le mouvement '.$event->movement->id, ['exception' => $e]);
        }
    }
}
