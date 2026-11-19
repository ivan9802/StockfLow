<?php

namespace App\Services;

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SaleStatus;
use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord, calculés sur la base (F4.1).
 *
 * Deux familles d'indicateurs :
 *  - l'état du stock, mesuré à l'instant (la base ne conserve pas de photographie
 *    du stock à une date passée : pas de comparaison possible) ;
 *  - les flux d'une période (ventes, entrées, sorties, transferts, ajustements),
 *    comparés à la période précédente (voir Period::previous()).
 *
 * Le périmètre dépend du profil : le vendeur ne voit que ses propres ventes et
 * jamais la valeur du stock ni les mouvements.
 * Règles détaillées : docs/10-ui-ux-dashboard/dashboard-indicators.md.
 */
final class DashboardService
{
    /** Sens de lecture d'une hausse, pour colorer l'évolution. */
    private const POLARITY = [
        'sales_count' => 'up_good',
        'revenue' => 'up_good',
        'average_basket' => 'up_good',
        'entries_units' => 'neutral',
        'exits_units' => 'neutral',
        'transfers_count' => 'neutral',
        'adjustments_count' => 'up_bad',
    ];

    public function __construct(private readonly AlertService $alerts) {}

    // ------------------------------------------------------------ Vue d'ensemble

    /** Tout ce que le tableau de bord affiche pour une période, prêt à être envoyé en JSON. */
    public function forPeriod(Period $period, User $user, bool $compare = true): array
    {
        $full = $user->can('dashboard.full');
        $previous = $period->previous();

        $current = $this->flows($period, $user);
        $before = $compare ? $this->flows($previous, $user) : [];

        $kpis = [];
        foreach ($current as $key => $value) {
            $kpis[$key] = $this->compare($key, $value, $compare ? ($before[$key] ?? null) : null);
        }

        return [
            'period' => $period->toArray(),
            'previous' => $compare ? $previous->toArray() : null,
            'snapshot' => $this->snapshot($user),
            'kpis' => $kpis,
            // Seules les séries interdites au profil sont retirées (null) : une série vide
            // reste présente, pour que l'écran affiche « Aucune donnée » au lieu d'échouer.
            'charts' => array_filter([
                'sales' => $this->salesSeries($period, $user),
                'movements' => $full ? $this->movementSeries($period) : null,
                'warehouses' => $full ? $this->byWarehouse() : null,
                'stock_status' => $this->stockStatusDistribution(),
            ], fn ($series) => $series !== null),
            'top_products' => $this->topProducts($period, $user),
        ];
    }

    // ------------------------------------------------------------ État du stock, à l'instant

    public function snapshot(User $user): array
    {
        $totals = DB::table('stock_levels')->selectRaw('product_id, SUM(quantity) AS total')->groupBy('product_id');

        $row = DB::table('products as p')
            ->whereNull('p.deleted_at')->where('p.is_active', true)
            ->leftJoinSub($totals, 't', 't.product_id', '=', 'p.id')
            ->selectRaw('COUNT(*) AS active_products')
            ->selectRaw('COALESCE(SUM(t.total), 0) AS total_units')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) * p.purchase_price), 0) AS stock_value')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) = 0), 0) AS out_count')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) > 0 AND COALESCE(t.total, 0) <= p.alert_threshold), 0) AS low_count')
            ->first();

        $snapshot = [
            'active_products' => (int) $row->active_products,
            'total_units' => (int) $row->total_units,
            'low_count' => (int) $row->low_count,
            'out_count' => (int) $row->out_count,
        ];

        if ($user->can('dashboard.full')) {
            $snapshot['stock_value'] = (int) $row->stock_value;
            $snapshot['pending_orders'] = PurchaseOrder::whereIn('status', [
                PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value,
            ])->count();
        }

        return $snapshot;
    }

    // ------------------------------------------------------------ Flux d'une période

    /** @return array<string, int> */
    public function flows(Period $period, User $user): array
    {
        $sales = $this->salesQuery($period, $user)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS revenue')
            ->toBase()->first();

        $flows = [
            'sales_count' => (int) $sales->n,
            'revenue' => (int) $sales->revenue,
            // Panier moyen arrondi au franc : le XOF n'a pas de centimes.
            'average_basket' => $sales->n > 0 ? (int) round($sales->revenue / $sales->n) : 0,
        ];

        if (! $user->can('dashboard.full')) {
            return $flows;
        }

        // Une seule lecture des mouvements de la période, agrégée par type et par sens.
        $movements = StockMovement::query()
            ->whereBetween('created_at', $period->bounds())
            ->groupBy('type', 'direction')
            ->selectRaw('type, direction, COUNT(*) AS n, SUM(quantity) AS units')
            ->toBase()->get();

        // Entrées et sorties hors transferts : un transfert déplace le stock sans le
        // faire entrer ni sortir de l'entreprise.
        $notTransfer = $movements->where('type', '!=', MovementType::Transfer->value);

        return $flows + [
            'entries_units' => (int) $notTransfer->where('direction', MovementDirection::In->value)->sum('units'),
            'exits_units' => (int) $notTransfer->where('direction', MovementDirection::Out->value)->sum('units'),
            'transfers_count' => DB::table('stock_transfers')
                ->where('status', TransferStatus::Completed->value)
                ->whereBetween('transferred_at', $period->bounds())->count(),
            'adjustments_count' => (int) $movements->whereIn('type', [
                MovementType::ManualAdjustment->value, MovementType::InventoryAdjustment->value,
            ])->sum('n'),
        ];
    }

    /**
     * Valeur, valeur de référence, variation en pourcentage et lecture de la variation.
     * Variation non calculable (null) quand la référence vaut zéro.
     */
    public function compare(string $key, int $value, ?int $previous): array
    {
        $change = ($previous !== null && $previous !== 0)
            ? round(($value - $previous) * 100 / $previous, 1)
            : null;

        $trend = match (true) {
            $previous === null => null,
            $value > $previous => 'up',
            $value < $previous => 'down',
            default => 'flat',
        };

        $tone = match (true) {
            $trend === null || $trend === 'flat' || self::POLARITY[$key] === 'neutral' => 'neutral',
            self::POLARITY[$key] === 'up_good' => $trend === 'up' ? 'good' : 'bad',
            default => $trend === 'up' ? 'bad' : 'good',
        };

        return ['value' => $value, 'previous' => $previous, 'change' => $change, 'trend' => $trend, 'tone' => $tone];
    }

    // ------------------------------------------------------------ Séries des graphiques

    /** Chiffre d'affaires et nombre de ventes par intervalle. */
    public function salesSeries(Period $period, User $user): array
    {
        $expr = $period->bucketExpression('sold_at');
        $rows = $this->salesQuery($period, $user)
            ->selectRaw("$expr AS bucket, COUNT(*) AS n, SUM(total_amount) AS revenue")
            ->groupByRaw($expr)
            ->toBase()->get()->keyBy('bucket');

        return $this->fillBuckets($period, fn (string $key) => [
            'revenue' => (int) ($rows[$key]->revenue ?? 0),
            'count' => (int) ($rows[$key]->n ?? 0),
        ]);
    }

    /** Unités entrées et sorties par intervalle, hors transferts. */
    public function movementSeries(Period $period): array
    {
        $expr = $period->bucketExpression('created_at');
        $rows = StockMovement::query()
            ->whereBetween('created_at', $period->bounds())
            ->where('type', '!=', MovementType::Transfer->value)
            ->selectRaw("$expr AS bucket, direction, SUM(quantity) AS units")
            ->groupByRaw("$expr, direction")
            ->toBase()->get()
            ->groupBy('bucket');

        return $this->fillBuckets($period, fn (string $key) => [
            'in' => (int) ($rows->get($key)?->firstWhere('direction', 'in')->units ?? 0),
            'out' => (int) ($rows->get($key)?->firstWhere('direction', 'out')->units ?? 0),
        ]);
    }

    /** Répartition des unités et de la valeur par emplacement, à l'instant. */
    public function byWarehouse(): array
    {
        $rows = Warehouse::query()->where('warehouses.is_active', true)->orderBy('warehouses.id')
            ->leftJoin('stock_levels', 'stock_levels.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('products', fn ($j) => $j->on('products.id', '=', 'stock_levels.product_id')->whereNull('products.deleted_at'))
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.id, warehouses.name, COALESCE(SUM(stock_levels.quantity), 0) AS units, COALESCE(SUM(stock_levels.quantity * products.purchase_price), 0) AS value')
            ->get();

        $totalUnits = max(1, (int) $rows->sum('units'));

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'units' => (int) $row->units,
            'value' => (int) $row->value,
            'percent' => (int) round($row->units * 100 / $totalUnits),
        ])->all();
    }

    /** Nombre de produits actifs en stock normal, sous le seuil et en rupture, à l'instant. */
    public function stockStatusDistribution(): array
    {
        $snapshot = DB::table('products as p')
            ->whereNull('p.deleted_at')->where('p.is_active', true)
            ->leftJoinSub(DB::table('stock_levels')->selectRaw('product_id, SUM(quantity) AS total')->groupBy('product_id'), 't', 't.product_id', '=', 'p.id')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) > p.alert_threshold), 0) AS ok')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) > 0 AND COALESCE(t.total, 0) <= p.alert_threshold), 0) AS low')
            ->selectRaw('COALESCE(SUM(COALESCE(t.total, 0) = 0), 0) AS out_count')
            ->first();

        return [
            ['key' => Product::STATUS_OK, 'label' => 'En stock', 'count' => (int) $snapshot->ok],
            ['key' => Product::STATUS_LOW, 'label' => 'Sous le seuil', 'count' => (int) $snapshot->low],
            ['key' => Product::STATUS_OUT, 'label' => 'En rupture', 'count' => (int) $snapshot->out_count],
        ];
    }

    /** Produits les plus vendus sur la période, en unités, ventes annulées exclues. */
    public function topProducts(Period $period, User $user, int $limit = 5): array
    {
        return DB::table('sale_items as i')
            ->join('sales as s', 's.id', '=', 'i.sale_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('s.status', SaleStatus::Completed->value)
            ->whereBetween('s.sold_at', $period->bounds())
            ->when(! $user->can('sales.view-all'), fn ($q) => $q->where('s.user_id', $user->id))
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->selectRaw('p.id, p.name, p.sku, SUM(i.quantity) AS units, SUM(i.line_total) AS revenue')
            ->orderByDesc('units')->orderByDesc('revenue')->orderBy('p.id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'name' => $r->name, 'sku' => $r->sku,
                'units' => (int) $r->units, 'revenue' => (int) $r->revenue,
                'url' => route('products.show', $r->id),
            ])->all();
    }

    // ------------------------------------------------------------ Listes d'activité

    public function latestMovements(int $limit = 6): Collection
    {
        return StockMovement::with(['product:id,name,sku', 'warehouse:id,name', 'user:id,name'])
            ->latest('id')->limit($limit)->get();
    }

    public function latestSales(User $user, int $limit = 6): Collection
    {
        return Sale::with('user:id,name')
            ->when(! $user->can('sales.view-all'), fn ($q) => $q->where('user_id', $user->id))
            ->latest('sold_at')->latest('id')->limit($limit)->get();
    }

    public function toReorder(int $limit = 6): Collection
    {
        return $this->alerts->productsBelowThreshold($limit);
    }

    // ------------------------------------------------------------ Outils

    private function salesQuery(Period $period, User $user): Builder
    {
        return Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', $period->bounds())
            ->when(! $user->can('sales.view-all'), fn ($q) => $q->where('user_id', $user->id));
    }

    /** Une entrée par intervalle de la période, y compris les intervalles sans donnée. */
    private function fillBuckets(Period $period, callable $values): array
    {
        $series = [];
        foreach ($period->buckets() as $key => $label) {
            $series[] = ['key' => $key, 'label' => $label] + $values((string) $key);
        }

        return $series;
    }
}
