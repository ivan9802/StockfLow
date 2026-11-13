<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'reference', 'supplier_id', 'warehouse_id', 'user_id', 'status',
        'ordered_at', 'expected_at', 'notes', 'closure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
            'expected_at' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /** Montant commandé, en F CFA. */
    public function totalAmount(): int
    {
        return (int) $this->items->sum(fn (PurchaseOrderItem $item) => $item->quantity_ordered * $item->unit_cost);
    }

    /** Part reçue de la commande, de 0 à 100. */
    public function receivedPercent(): int
    {
        $ordered = (int) $this->items->sum('quantity_ordered');

        return $ordered === 0 ? 0 : (int) floor($this->items->sum('quantity_received') * 100 / $ordered);
    }

    public function auditLabel(): string
    {
        return 'Commande '.$this->reference;
    }
}
