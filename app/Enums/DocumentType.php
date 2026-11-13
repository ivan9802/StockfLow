<?php

namespace App\Enums;

/**
 * Types de documents numérotés. La valeur est le préfixe de la référence,
 * par exemple BS-2026-000128 pour un bon de sortie.
 */
enum DocumentType: string
{
    case PurchaseOrder = 'CF';
    case GoodsReceipt = 'BR';
    case Sale = 'BS';
    case Transfer = 'TR';
    case Inventory = 'INV';
    case ManualMovement = 'BM';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseOrder => 'Commande fournisseur',
            self::GoodsReceipt => 'Bon de réception',
            self::Sale => 'Bon de sortie',
            self::Transfer => 'Bon de transfert',
            self::Inventory => 'Inventaire',
            self::ManualMovement => 'Bon de mouvement',
        };
    }
}
