<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Warehouse = 'warehouse';
    case Store = 'store';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Entrepôt',
            self::Store => 'Point de vente',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
