<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'product_id', 'purchase_order_item_id', 'quantity', 'unit_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'integer'];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
