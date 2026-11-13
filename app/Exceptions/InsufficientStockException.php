<?php

namespace App\Exceptions;

/**
 * Une ou plusieurs lignes demandent plus que le stock disponible.
 * L'opération entière est refusée : rien n'est écrit.
 */
class InsufficientStockException extends BusinessException
{
    /**
     * @param  array<int, array{product_id: int, product_name: string, sku: string, warehouse_id: int, requested: int, available: int}>  $shortages
     */
    public function __construct(private readonly array $shortages)
    {
        $lines = array_map(
            fn (array $s) => sprintf(
                '%s : %d disponible%s, %d demandé%s',
                $s['product_name'],
                $s['available'], $s['available'] > 1 ? 's' : '',
                $s['requested'], $s['requested'] > 1 ? 's' : '',
            ),
            $shortages,
        );

        parent::__construct('Stock insuffisant pour '.implode(' ; ', $lines).'.');
    }

    /** @return array<int, array{product_id: int, product_name: string, sku: string, warehouse_id: int, requested: int, available: int}> */
    public function shortages(): array
    {
        return $this->shortages;
    }

    public function details(): array
    {
        return ['shortages' => $this->shortages];
    }
}
