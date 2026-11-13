<?php

namespace App\Models;

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ligne d'historique de stock, en ajout seul. Créée uniquement par StockService.
 */
class StockMovement extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'warehouse_id', 'user_id', 'type', 'direction', 'quantity',
        'stock_before', 'stock_after', 'reason', 'reference', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'direction' => MovementDirection::class,
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Variation signée du stock : +5 pour une entrée de 5, -5 pour une sortie de 5. */
    public function signedQuantity(): int
    {
        return $this->direction->sign() * $this->quantity;
    }
}
