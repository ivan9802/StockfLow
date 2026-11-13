<?php

namespace App\Models;

use App\Enums\WarehouseType;
use App\Models\Concerns\Auditable;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['code', 'name', 'type', 'address', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Un nouvel emplacement reçoit une ligne de stock à zéro pour chaque produit.
        static::created(fn (Warehouse $warehouse) => app(StockService::class)->initializeLevels($warehouse));
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Emplacements d'où une vente peut partir. */
    public function scopeStores(Builder $query): Builder
    {
        return $query->where('type', WarehouseType::Store->value);
    }

    public function isStore(): bool
    {
        return $this->type === WarehouseType::Store;
    }

    public function auditLabel(): string
    {
        return 'Emplacement '.$this->name;
    }
}
