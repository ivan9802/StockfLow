<?php

namespace App\Exceptions;

use LogicException;

/**
 * Tentative de modifier ou de supprimer un enregistrement d'historique
 * (mouvement de stock, ligne d'audit). C'est une erreur de programmation.
 */
class ImmutableRecordException extends LogicException {}
