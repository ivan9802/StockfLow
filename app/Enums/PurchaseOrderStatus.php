<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Ordered => 'Commandée',
            self::PartiallyReceived => 'Reçue en partie',
            self::Received => 'Reçue',
            self::Closed => 'Soldée',
            self::Cancelled => 'Annulée',
        };
    }

    /** Une réception n'est possible que sur une commande passée et non terminée. */
    public function canReceive(): bool
    {
        return in_array($this, [self::Ordered, self::PartiallyReceived], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
