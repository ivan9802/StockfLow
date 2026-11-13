<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    protected $fillable = [
        'reference', 'warehouse_id', 'category_id', 'status', 'started_by', 'started_at',
        'submitted_at', 'validated_by', 'validated_at', 'notes', 'review_comment', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function auditLabel(): string
    {
        return 'Inventaire '.$this->reference;
    }
}
