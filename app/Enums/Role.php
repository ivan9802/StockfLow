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

    /**
     * Matrice des droits (docs/01-analysis/requirements.md, décisions D-005 et D-009).
     * Chaque droit devient une Gate dans AppServiceProvider et se vérifie côté
     * serveur sur chaque route, y compris les points JSON appelés par Fetch.
     *
     * @return array<string, array<int, self>>
     */
    public static function permissions(): array
    {
        $all = [self::Admin, self::StockManager, self::SalesAgent];
        $managers = [self::Admin, self::StockManager];
        $admin = [self::Admin];

        return [
            // Consultation ouverte aux trois profils.
            'catalog.view' => $all,
            'stock.view' => $all,
            'sales.view' => $all,            // le vendeur ne voit que ses ventes (SalePolicy)

            // Gestion du stock : administrateur et gestionnaire.
            'catalog.manage' => $managers,
            'suppliers.manage' => $managers,
            'purchases.manage' => $managers,
            'movements.view' => $managers,
            'movements.manage' => $managers,
            'transfers.manage' => $managers,
            'inventories.manage' => $managers,
            'sales.view-all' => $managers,
            'sales.cancel' => $managers,
            'prices.view-purchase' => $managers,
            'alerts.view' => $managers,
            'reports.view' => $managers,
            'dashboard.full' => $managers,

            // Le gestionnaire ne saisit pas de vente (D-009).
            'sales.create' => [self::Admin, self::SalesAgent],

            // Réservé à l'administrateur.
            'inventories.validate' => $admin,
            'users.manage' => $admin,
            'warehouses.manage' => $admin,
            'audit.view' => $admin,
        ];
    }

    public function allows(string $ability): bool
    {
        return in_array($this, self::permissions()[$ability] ?? [], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
