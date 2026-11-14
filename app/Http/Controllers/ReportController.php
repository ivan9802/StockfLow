<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\CsvWriter;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** États de stock et historiques, en CSV et en PDF, pour les contrôles comptables (F4.3). */
class ReportController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        return view('reports.index', [
            'warehouses' => Warehouse::orderBy('id')->pluck('name', 'id'),
            'pdfMax' => (int) config('stockflow.pdf_max_rows'),
        ]);
    }

    /** État des stocks à l'instant de l'export. */
    public function stock(Request $request, string $format): Response|RedirectResponse
    {
        $warehouses = Warehouse::active()->orderBy('id')->get();
        $products = Product::query()->with(['category:id,name', 'stockLevels'])->withTotalStock()->orderBy('name');

        $this->audit->record(AuditAction::Exported, null, 'Export de l\'état des stocks ('.strtoupper($format).')');
        $stamp = now()->format('Y-m-d_H-i');

        if ($format === 'csv') {
            $headers = array_merge(['Référence', 'Code-barres', 'Produit', 'Catégorie', 'Actif'],
                $warehouses->map(fn ($w) => 'Stock '.$w->name)->all(),
                ['Stock total', 'Seuil d\'alerte', 'État', 'Prix d\'achat (F CFA)', 'Valeur (F CFA)']);

            $rows = (function () use ($products, $warehouses) {
                foreach ($products->lazy(200) as $product) {
                    $levels = $product->stockLevels->keyBy('warehouse_id');
                    $total = $product->totalStock();
                    yield array_merge(
                        [$product->sku, $product->barcode, $product->name, $product->category->name, $product->is_active ? 'oui' : 'non'],
                        $warehouses->map(fn ($w) => $levels[$w->id]->quantity ?? 0)->all(),
                        [$total, $product->alert_threshold, $this->statusLabel($product->stockStatus()), $product->purchase_price, $total * $product->purchase_price],
                    );
                }
            })();

            return CsvWriter::download("etat-des-stocks_$stamp.csv", $headers, $rows);
        }

        if ($products->count() > config('stockflow.pdf_max_rows')) {
            return back()->with('warning', 'Trop de lignes pour un PDF lisible : utilisez l\'export CSV.');
        }

        $list = $products->get();

        return Pdf::loadView('reports.pdf.stock', [
            'products' => $list,
            'warehouses' => $warehouses,
            'totalValue' => $list->sum(fn ($p) => $p->totalStock() * $p->purchase_price),
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download("etat-des-stocks_$stamp.pdf");
    }

    /** Historique des mouvements, avec les mêmes filtres que l'écran d'historique. */
    public function movements(Request $request, string $format): Response|RedirectResponse
    {
        $filters = app(MovementController::class)->filters($request);
        $query = MovementController::filteredQuery($filters)
            ->with(['product' => fn ($q) => $q->withTrashed()->select('id', 'sku', 'name'), 'warehouse:id,name', 'user' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->orderBy('id');

        $this->audit->record(AuditAction::Exported, null, 'Export de l\'historique des mouvements ('.strtoupper($format).')', [], $filters);
        $stamp = now()->format('Y-m-d_H-i');

        if ($format === 'csv') {
            $rows = (function () use ($query) {
                foreach ($query->lazy(500) as $m) {
                    yield [
                        $m->created_at->format('d/m/Y H:i'), $m->reference, $m->type->label(), $m->direction->label(),
                        $m->product->sku, $m->product->name, $m->warehouse->name, $m->signedQuantity(),
                        $m->stock_before, $m->stock_after, $m->reason, $m->user->name,
                    ];
                }
            })();

            return CsvWriter::download("mouvements_$stamp.csv",
                ['Date', 'Référence', 'Type', 'Sens', 'Code produit', 'Produit', 'Emplacement', 'Quantité', 'Stock avant', 'Stock après', 'Motif', 'Utilisateur'],
                $rows);
        }

        if ($query->count() > config('stockflow.pdf_max_rows')) {
            return back()->with('warning', 'Plus de '.config('stockflow.pdf_max_rows').' mouvements : restreignez la période ou utilisez l\'export CSV.');
        }

        return Pdf::loadView('reports.pdf.movements', ['movements' => $query->get(), 'filters' => $filters])
            ->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download("mouvements_$stamp.pdf");
    }

    /** Ventes, une ligne par article vendu. */
    public function sales(Request $request): Response
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        $sales = Sale::query()
            ->with(['items.product' => fn ($q) => $q->withTrashed(), 'user' => fn ($q) => $q->withTrashed(), 'warehouse'])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('sold_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('sold_at', '<=', $d.' 23:59:59'))
            ->orderBy('id');

        $this->audit->record(AuditAction::Exported, null, 'Export des ventes (CSV)', [], $filters);

        $rows = (function () use ($sales) {
            foreach ($sales->lazy(200) as $sale) {
                foreach ($sale->items as $item) {
                    yield [
                        $sale->sold_at->format('d/m/Y H:i'), $sale->reference, $sale->status->label(), $sale->warehouse->name,
                        $sale->user->name, $sale->customer_name, $item->product->sku, $item->product->name,
                        $item->quantity, $item->unit_price, $item->line_total,
                    ];
                }
            }
        })();

        return CsvWriter::download('ventes_'.now()->format('Y-m-d_H-i').'.csv',
            ['Date', 'Bon de sortie', 'Statut', 'Point de vente', 'Vendeur', 'Client', 'Code produit', 'Produit', 'Quantité', 'Prix unitaire (F CFA)', 'Montant (F CFA)'],
            $rows);
    }

    private function statusLabel(string $status): string
    {
        return [Product::STATUS_OUT => 'Rupture', Product::STATUS_LOW => 'Sous seuil', Product::STATUS_OK => 'En stock'][$status];
    }
}
