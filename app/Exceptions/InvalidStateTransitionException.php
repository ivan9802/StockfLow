<?php

namespace App\Exceptions;

/** Transition d'état interdite sur un document (par exemple annuler une vente déjà annulée). */
class InvalidStateTransitionException extends BusinessException {}
