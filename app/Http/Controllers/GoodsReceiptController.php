<?php

namespace App\Http\Controllers;

use App\Http\Requests\DirectReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'origin' => ['nullable', 'in:order,direct'],
        ]);

        $receipts = GoodsReceipt::query()
            ->with(['supplier:id,name', 'warehouse:id,name', 'user:id,name', 'purchaseOrder:id,reference'])
            ->withSum('items as units', 'quantity')
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(($filters['origin'] ?? null) === 'order', fn ($q) => $q->whereNotNull('purchase_order_id'))
            ->when(($filters['origin'] ?? null) === 'direct', fn ($q) => $q->whereNull('purchase_order_id'))
            ->latest('received_at')->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'goods-receipts.index', 'goods-receipts._table', [
            'receipts' => $receipts,
            'filters' => $filters,
            'suppliers' => Supplier::withTrashed()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Réception directe, sans commande (D-016). */
    public function create(): View
    {
        return view('goods-receipts.create', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('id')->pluck('name', 'id'),
        ]);
    }

    public function store(DirectReceiptRequest $request): RedirectResponse
    {
        $receipt = $this->purchases->receiveDirect(
            Supplier::findOrFail($request->integer('supplier_id')),
            Warehouse::findOrFail($request->integer('warehouse_id')),
            $request->lines(),
            $request->safe()->only(['delivery_note_number', 'received_at', 'notes']),
            $request->user(),
        );

        return redirect()->route('goods-receipts.show', $receipt)->with('success', "Réception {$receipt->reference} enregistrée.");
    }

    /** Formulaire de réception d'une commande : une ligne par article encore attendu. */
    public function createFromOrder(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->status->canReceive()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('warning', "La commande est « {$purchaseOrder->status->label()} » : elle ne peut pas être réceptionnée.");
        }

        return view('goods-receipts.from-order', ['order' => $purchaseOrder->load(['supplier', 'warehouse', 'items.product'])]);
    }

    public function storeFromOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'delivery_note_number' => ['nullable', 'string', 'max:60'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['quantities.*' => 'quantité reçue']);

        $lines = collect($data['quantities'])
            ->map(fn ($quantity, $itemId) => ['purchase_order_item_id' => (int) $itemId, 'quantity' => (int) $quantity])
            ->values()->all();

        $receipt = $this->purchases->receive($purchaseOrder, $lines, $data, $request->user());

        return redirect()->route('goods-receipts.show', $receipt)->with('success', "Réception {$receipt->reference} enregistrée.");
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        return view('goods-receipts.show', ['receipt' => $this->loaded($goodsReceipt)]);
    }

    public function print(GoodsReceipt $goodsReceipt): View
    {
        return view('goods-receipts.print', ['receipt' => $this->loaded($goodsReceipt)]);
    }

    private function loaded(GoodsReceipt $receipt): GoodsReceipt
    {
        return $receipt->load(['supplier', 'warehouse', 'user', 'purchaseOrder', 'items.product']);
    }
}
