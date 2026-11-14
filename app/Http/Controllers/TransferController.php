<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReasonRequest;
use App\Http\Requests\TransferRequest;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:completed,cancelled'],
            'from_warehouse_id' => ['nullable', 'integer'],
        ]);

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'user:id,name'])
            ->withSum('items as units', 'quantity')
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from_warehouse_id'] ?? null, fn ($q, $id) => $q->where('from_warehouse_id', $id))
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'transfers.index', 'transfers._table', [
            'transfers' => $transfers,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('id')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $warehouses = Warehouse::active()->orderBy('id')->get();

        return view('transfers.create', [
            'warehouses' => $warehouses->pluck('name', 'id'),
            // Sens habituel : de l'entrepôt vers la boutique.
            'defaultFrom' => $warehouses->firstWhere('type.value', 'warehouse')?->id,
            'defaultTo' => $warehouses->firstWhere('type.value', 'store')?->id,
        ]);
    }

    public function store(TransferRequest $request): RedirectResponse
    {
        $transfer = $this->transfers->execute(
            Warehouse::findOrFail($request->integer('from_warehouse_id')),
            Warehouse::findOrFail($request->integer('to_warehouse_id')),
            $request->lines(),
            $request->validated('notes'),
            $request->user(),
        );

        return redirect()->route('transfers.show', $transfer)->with('success', "Transfert {$transfer->reference} effectué.");
    }

    public function show(StockTransfer $transfer): View
    {
        return view('transfers.show', ['transfer' => $this->loaded($transfer)]);
    }

    public function print(StockTransfer $transfer): View
    {
        return view('transfers.print', ['transfer' => $this->loaded($transfer)]);
    }

    public function cancel(ReasonRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->transfers->cancel($transfer, $request->user(), $request->validated('reason'));

        return redirect()->route('transfers.show', $transfer)->with('success', "Transfert {$transfer->reference} annulé : la marchandise est revenue à son emplacement de départ.");
    }

    private function loaded(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load(['fromWarehouse', 'toWarehouse', 'user', 'cancelledBy', 'items.product']);
    }
}
