<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SaleStatus;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord, calculés en direct sur la base (F4.1).
 * Valeur du stock : quantités valorisées au prix d'achat courant de la fiche produit.
 */
final class DashboardService
{
    public function __construct(private readonly AlertService $alerts) {}

    public function kpis(User $user): array
    {
        $today = [today()->startOfDay(), today()->endOfDay()];

        $activeProducts = DB::table('products')->whereNull('deleted_at')->where('is_active', true);
        $totals = DB::table('stock_levels')
            ->selectRaw('product_id, SUM(quantity) AS total')
            ->groupBy('product_id');

        $statusCounts = DB::query()
            ->fromSub($activeProducts->clone()->select('products.id', 'products.alert_threshold'), 'p')
            ->leftJoinSub($totals, 't', 't.product_id', '=', 'p.id')
            ->selectRaw('SUM(COALESCE(t.total, 0) = 0) AS out_count')
            ->selectRaw('SUM(COALESCE(t.total, 0) > 0 AND COALESCE(t.total, 0) <= p.alert_threshold) AS low_count')
            ->first();

        $salesToday = Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', $today)
            ->when(! $user->can('sales.view-all'), fn ($q) => $q->where('user_id', $user->id));

        $kpis = [
            'active_products' => $activeProducts->clone()->count(),
            'low_count' => (int) $statusCounts->low_count,
            'out_count' => (int) $statusCounts->out_count,
            'sales_today_count' => $salesToday->clone()->count(),
            'sales_today_amount' => (int) $salesToday->clone()->sum('total_amount'),
        ];

        if ($user->can('dashboard.full')) {
            $kpis['stock_value'] = (int) DB::table('stock_levels')
                ->join('products', 'products.id', '=', 'stock_levels.product_id')
                ->whereNull('products.deleted_at')
                ->sum(DB::raw('stock_levels.quantity * products.purchase_price'));
            $kpis['movements_today'] = StockMovement::whereBetween('created_at', $today)->count();
            $kpis['pending_orders'] = PurchaseOrder::whereIn('status', [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value])->count();
        }

        return $kpis;
    }

    /** Répartition des quantités et de la valeur par emplacement. */
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

    public function latestMovements(int $limit = 8)
    {
        return StockMovement::with(['product:id,name,sku', 'warehouse:id,name', 'user:id,name'])
            ->latest('id')->limit($limit)->get();
    }

    public function toReorder(int $limit = 8)
    {
        return $this->alerts->productsBelowThreshold($limit);
    }
}
