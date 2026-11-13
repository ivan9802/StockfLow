<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur de règle métier. Son message est écrit en français pour l'utilisateur
 * et peut être affiché tel quel. Rendue en HTTP 422 (voir bootstrap/app.php).
 */
class BusinessException extends RuntimeException
{
    /** Détails structurés renvoyés avec la réponse JSON. */
    public function details(): array
    {
        return [];
    }
}
