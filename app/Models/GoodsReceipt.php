<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'reference', 'purchase_order_id', 'supplier_id', 'warehouse_id', 'user_id',
        'delivery_note_number', 'notes', 'received_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function totalAmount(): int
    {
        return (int) $this->items->sum(fn (GoodsReceiptItem $item) => $item->quantity * $item->unit_cost);
    }

    public function auditLabel(): string
    {
        return 'Réception '.$this->reference;
    }
}
