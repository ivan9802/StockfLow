<?php

namespace App\Enums;

/**
 * Nature métier d'un mouvement de stock.
 * Correspondance avec le cahier des charges : IN = purchase, sale_cancellation ;
 * OUT = sale, internal_use ; ADJ = manual_adjustment, inventory_adjustment ;
 * transfer produit une sortie et une entrée de même référence.
 */
enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleCancellation = 'sale_cancellation';
    case InternalUse = 'internal_use';
    case Transfer = 'transfer';
    case ManualAdjustment = 'manual_adjustment';
    case InventoryAdjustment = 'inventory_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Réception fournisseur',
            self::Sale => 'Vente',
            self::SaleCancellation => 'Annulation de vente',
            self::InternalUse => 'Sortie interne',
            self::Transfer => 'Transfert',
            self::ManualAdjustment => 'Ajustement manuel',
            self::InventoryAdjustment => 'Régularisation d\'inventaire',
        };
    }

    /** Un motif écrit est obligatoire pour les opérations sensibles. */
    public function requiresReason(): bool
    {
        return in_array($this, [
            self::SaleCancellation,
            self::InternalUse,
            self::ManualAdjustment,
            self::InventoryAdjustment,
        ], true);
    }

    /**
     * Sens autorisés pour ce type. Un type à sens unique ne peut pas être
     * enregistré dans l'autre sens, même par erreur de programmation.
     *
     * @return array<int, MovementDirection>
     */
    public function allowedDirections(): array
    {
        return match ($this) {
            self::Purchase, self::SaleCancellation => [MovementDirection::In],
            self::Sale, self::InternalUse => [MovementDirection::Out],
            self::Transfer, self::ManualAdjustment, self::InventoryAdjustment => [MovementDirection::In, MovementDirection::Out],
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
