<?php

namespace App\Enums;

enum MovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrée',
            self::Out => 'Sortie',
        };
    }

    /** Signe à appliquer à la quantité pour obtenir la variation du stock. */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
