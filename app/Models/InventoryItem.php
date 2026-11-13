<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_session_id', 'product_id', 'theoretical_quantity', 'counted_quantity',
        'variance', 'counted_at', 'counted_by', 'justification',
    ];

    protected function casts(): array
    {
        return [
            'theoretical_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'variance' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by')->withTrashed();
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function hasVariance(): bool
    {
        return $this->isCounted() && $this->variance !== 0;
    }
}
