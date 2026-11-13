<?php

namespace App\Enums;

/**
 * Les trois profils de l'application. Ils sont fixes (décision D-005) :
 * l'administrateur attribue un profil, il n'en crée pas.
 */
enum Role: string
{
    case Admin = 'admin';
    case StockManager = 'stock_manager';
    case SalesAgent = 'sales_agent';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::StockManager => 'Gestionnaire de stock',
            self::SalesAgent => 'Vendeur',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
