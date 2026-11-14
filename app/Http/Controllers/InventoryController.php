<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Http\Requests\ReasonRequest;
use App\Models\Category;
use App\Models\InventorySession;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventories) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:'.implode(',', InventoryStatus::values())]]);

        $sessions = InventorySession::query()
            ->with(['warehouse:id,name', 'category:id,name', 'startedBy:id,name', 'validatedBy:id,name'])
            ->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity'),
                'items as variance_count' => fn ($q) => $q->where('variance', '<>', 0)])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('inventories.index', [
            'sessions' => $sessions,
            'filters' => $filters,
            'statuses' => collect(InventoryStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }

    public function create(): View
    {
        return view('inventories.create', [
            'warehouses' => Warehouse::active()->orderBy('id')->pluck('name', 'id'),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->inventories->open(
            Warehouse::findOrFail($data['warehouse_id']),
            isset($data['category_id']) ? Category::find($data['category_id']) : null,
            $request->user(),
            $data['notes'] ?? null,
        );

        return redirect()->route('inventories.show', $session)->with('success', "Inventaire {$session->reference} ouvert. Vous pouvez commencer le comptage.");
    }

    /** Comptage (session en cours) ou revue des écarts (session soumise ou close). */
    public function show(Request $request, InventorySession $inventory): View
    {
        $inventory->load(['warehouse', 'category', 'startedBy', 'validatedBy']);

        $items = $inventory->items()
            ->with(['product:id,sku,name,barcode', 'countedBy:id,name'])
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->when($request->input('show') === 'uncounted', fn ($q) => $q->whereNull('counted_quantity'))
            ->when($request->input('show') === 'variance', fn ($q) => $q->where('variance', '<>', 0))
            ->orderBy('products.name')
            ->select('inventory_items.*')
            ->get();

        // Stock courant, pour signaler les produits qui ont bougé depuis leur comptage.
        $current = StockLevel::where('warehouse_id', $inventory->warehouse_id)
            ->whereIn('product_id', $items->pluck('product_id'))->pluck('quantity', 'product_id');

        return view('inventories.show', [
            'session' => $inventory,
            'items' => $items,
            'current' => $current,
            'show' => $request->input('show'),
            'stats' => [
                'total' => $inventory->items()->count(),
                'counted' => $inventory->items()->whereNotNull('counted_quantity')->count(),
                'variances' => $inventory->items()->where('variance', '<>', 0)->count(),
            ],
        ]);
    }

    /** Feuille de comptage imprimable, sans quantité théorique (comptage à l'aveugle). */
    public function print(InventorySession $inventory): View
    {
        return view('inventories.print', [
            'session' => $inventory->load(['warehouse', 'category']),
            'items' => $inventory->items()->with('product:id,sku,name,barcode')
                ->join('products', 'products.id', '=', 'inventory_items.product_id')
                ->orderBy('products.name')->select('inventory_items.*')->get(),
        ]);
    }

    /** Enregistre des comptages. Appelé par le formulaire ou par Fetch, ligne par ligne. */
    public function counts(Request $request, InventorySession $inventory): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'counts' => ['required', 'array', 'max:2000'],
            'counts.*.counted_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'counts.*.justification' => ['nullable', 'string', 'max:500'],
        ], [], ['counts.*.counted_quantity' => 'quantité comptée', 'counts.*.justification' => 'justification']);

        $this->inventories->recordCounts($inventory, $data['counts'], $request->user());

        if ($request->expectsJson()) {
            $items = $inventory->items()->whereIn('id', array_keys($data['counts']))->get(['id', 'theoretical_quantity', 'counted_quantity', 'variance', 'justification']);

            return response()->json([
                'items' => $items,
                'counted' => $inventory->items()->whereNotNull('counted_quantity')->count(),
                'total' => $inventory->items()->count(),
            ]);
        }

        return back()->with('success', 'Comptage enregistré.');
    }

    public function submit(Request $request, InventorySession $inventory): RedirectResponse
    {
        $this->inventories->submit($inventory, $request->user());

        return redirect()->route('inventories.show', $inventory)->with('success', "Inventaire {$inventory->reference} soumis à la validation de l'administrateur.");
    }

    public function sendBack(ReasonRequest $request, InventorySession $inventory): RedirectResponse
    {
        $this->inventories->sendBack($inventory, $request->user(), $request->validated('reason'));

        return redirect()->route('inventories.show', $inventory)->with('success', "Inventaire {$inventory->reference} renvoyé en comptage.");
    }

    public function validateSession(Request $request, InventorySession $inventory): RedirectResponse
    {
        $this->inventories->validate($inventory, $request->user());

        return redirect()->route('inventories.show', $inventory)->with('success', "Inventaire {$inventory->reference} validé : les écarts ont été régularisés.");
    }

    public function cancel(ReasonRequest $request, InventorySession $inventory): RedirectResponse
    {
        $this->inventories->cancel($inventory, $request->user(), $request->validated('reason'));

        return redirect()->route('inventories.show', $inventory)->with('success', "Inventaire {$inventory->reference} annulé.");
    }
}
