<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case InProgress = 'in_progress';
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Comptage en cours',
            self::PendingValidation => 'En attente de validation',
            self::Validated => 'Validé',
            self::Cancelled => 'Annulé',
        };
    }

    /** Une session ouverte bloque l'ouverture d'une autre sur le même emplacement. */
    public function isOpen(): bool
    {
        return in_array($this, [self::InProgress, self::PendingValidation], true);
    }

    /** @return array<int, string> */
    public static function openValues(): array
    {
        return [self::InProgress->value, self::PendingValidation->value];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
