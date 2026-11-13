<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantité disponible d'un produit dans un emplacement.
 * La colonne quantity n'est écrite que par App\Services\StockService.
 */
class StockLevel extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'reorder_point'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reorder_point' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
