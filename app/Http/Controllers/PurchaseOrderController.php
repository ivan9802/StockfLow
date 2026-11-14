<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', PurchaseOrderStatus::values()).',pending'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'warehouse:id,name', 'items:id,purchase_order_id,quantity_ordered,quantity_received,unit_cost'])
            ->when($filters['status'] ?? null, function ($q, $status) {
                // « En attente » : commandes passées qui attendent encore une livraison.
                $status === 'pending'
                    ? $q->whereIn('status', [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value])
                    : $q->where('status', $status);
            })
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'purchase-orders.index', 'purchase-orders._table', [
            'orders' => $orders,
            'filters' => $filters,
            'suppliers' => Supplier::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'statuses' => collect(PurchaseOrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.form', ['order' => new PurchaseOrder] + $this->formData());
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->purchases->createDraft($request->orderData(), $request->user());

        return redirect()->route('purchase-orders.show', $order)->with('success', "Commande {$order->reference} enregistrée en brouillon.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.show', [
            'order' => $purchaseOrder->load(['supplier', 'warehouse', 'user', 'items.product', 'receipts.user']),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->with('warning', 'Seule une commande en brouillon se modifie.');
        }

        return view('purchase-orders.form', ['order' => $purchaseOrder->load('items.product')] + $this->formData());
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->updateDraft($purchaseOrder, $request->orderData(), $request->user());

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', "Commande {$purchaseOrder->reference} modifiée.");
    }

    public function confirm(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->confirm($purchaseOrder, $request->user());

        return back()->with('success', "Commande {$purchaseOrder->reference} passée au fournisseur.");
    }

    public function cancel(ReasonRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->cancel($purchaseOrder, $request->user(), $request->validated('reason'));

        return back()->with('success', "Commande {$purchaseOrder->reference} annulée.");
    }

    public function close(ReasonRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->close($purchaseOrder, $request->user(), $request->validated('reason'));

        return back()->with('success', "Commande {$purchaseOrder->reference} soldée.");
    }

    private function formData(): array
    {
        return [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name', 'lead_time_days']),
            'warehouses' => Warehouse::active()->orderBy('id')->pluck('name', 'id'),
        ];
    }
}
