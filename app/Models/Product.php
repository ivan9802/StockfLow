<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** États de stock d'un produit, calculés sur le stock total. */
    public const STATUS_OUT = 'out';

    public const STATUS_LOW = 'low';

    public const STATUS_OK = 'ok';

    protected $fillable = [
        'sku', 'barcode', 'name', 'description', 'category_id', 'supplier_id',
        'purchase_price', 'selling_price', 'alert_threshold', 'image_path', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'integer',
            'selling_price' => 'integer',
            'alert_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Chaque produit a une ligne de stock à zéro dans chaque emplacement dès sa création.
        static::created(fn (Product $product) => app(StockService::class)->initializeLevels($product));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Recherche par préfixe de SKU ou de code-barres, ou par fragment du libellé.
     * Les caractères % et _ saisis sont traités comme du texte.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $escaped = addcslashes($term, '\\%_');

        return $query->where(function (Builder $q) use ($escaped) {
            $q->where('sku', 'like', $escaped.'%')
                ->orWhere('barcode', 'like', $escaped.'%')
                ->orWhere('name', 'like', '%'.$escaped.'%');
        });
    }

    /** Ajoute la colonne total_stock (somme des quantités de tous les emplacements). */
    public function scopeWithTotalStock(Builder $query): Builder
    {
        return $query->withSum('stockLevels as total_stock', 'quantity');
    }

    /** Filtre sur l'état de stock : out (rupture), low (sous seuil), ok. */
    public function scopeStockStatus(Builder $query, ?string $status): Builder
    {
        $total = '(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id)';

        return match ($status) {
            self::STATUS_OUT => $query->whereRaw("$total = 0"),
            self::STATUS_LOW => $query->whereRaw("$total > 0 AND $total <= products.alert_threshold"),
            self::STATUS_OK => $query->whereRaw("$total > products.alert_threshold"),
            default => $query,
        };
    }

    /** Stock total, tous emplacements confondus. */
    public function totalStock(): int
    {
        if (array_key_exists('total_stock', $this->attributes)) {
            return (int) $this->attributes['total_stock'];
        }

        return (int) ($this->relationLoaded('stockLevels')
            ? $this->stockLevels->sum('quantity')
            : $this->stockLevels()->sum('quantity'));
    }

    /** Rupture, sous seuil ou normal, d'après la règle quantité <= seuil (F4.2). */
    public function stockStatus(): string
    {
        $total = $this->totalStock();

        if ($total === 0) {
            return self::STATUS_OUT;
        }

        return $total <= $this->alert_threshold ? self::STATUS_LOW : self::STATUS_OK;
    }

    public function auditLabel(): string
    {
        return 'Produit '.$this->sku;
    }
}
