<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\SaleRequest;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    /** Point de vente. */
    public function create(Request $request): View
    {
        $user = $request->user();
        $stores = $this->storesFor($user);
        abort_if($stores->isEmpty(), 403, 'Aucun point de vente actif ne vous est affecté.');

        return view('sales.create', [
            'stores' => $stores,
            'store' => $stores->firstWhere('id', $request->integer('warehouse_id')) ?? $stores->first(),
            'formToken' => Str::random(40),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user, $request->integer('warehouse_id'));

        // Jeton à usage unique : un double clic ou un rechargement ne crée pas une seconde vente.
        if (! Cache::add('sale-form:'.$request->validated('form_token'), true, now()->addHours(12))) {
            $message = 'Cette vente a déjà été envoyée. Consultez l\'historique avant de la saisir à nouveau.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : redirect()->route('sales.index')->with('warning', $message);
        }

        try {
            $sale = $this->sales->create($store, $request->lines(), $request->validated('customer_name'), $user);
        } catch (BusinessException $e) {
            // La vente est refusée : le jeton redevient utilisable pour corriger le panier.
            Cache::forget('sale-form:'.$request->validated('form_token'));
            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Vente {$sale->reference} enregistrée.",
                'reference' => $sale->reference,
                'url' => route('sales.show', $sale),
                'print_url' => route('sales.print', $sale),
            ], 201);
        }

        return redirect()->route('sales.show', $sale)->with('success', "Vente {$sale->reference} enregistrée.");
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:completed,cancelled'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $all = $user->can('sales.view-all');

        $sales = Sale::query()
            ->with(['user:id,name', 'warehouse:id,name'])
            ->withSum('items as units', 'quantity')
            // Le vendeur ne voit que ses ventes, quel que soit le filtre demandé.
            ->when(! $all, fn ($q) => $q->where('user_id', $user->id))
            ->when($all && ($filters['user_id'] ?? null), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('reference', 'like', addcslashes($t, '\\%_').'%')
                ->orWhere('customer_name', 'like', '%'.addcslashes($t, '\\%_').'%')))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('sold_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('sold_at', '<=', $d.' 23:59:59'))
            ->latest('sold_at')->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'sales.index', 'sales._table', [
            'sales' => $sales,
            'filters' => $filters,
            'showSeller' => $all,
            'sellers' => $all ? User::withTrashed()->whereIn('id', Sale::select('user_id'))->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        return view('sales.show', ['sale' => $this->loaded($sale)]);
    }

    /** Bon de sortie imprimable. */
    public function print(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        return view('sales.print', ['sale' => $this->loaded($sale)]);
    }

    public function cancel(ReasonRequest $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('cancel', $sale);

        $this->sales->cancel($sale, $request->user(), $request->validated('reason'));

        return redirect()->route('sales.show', $sale)->with('success', "Vente {$sale->reference} annulée : le stock a été réintégré.");
    }

    /** Points de vente ouverts à l'utilisateur : son affectation pour un vendeur, tous pour l'administrateur. */
    private function storesFor(User $user)
    {
        return Warehouse::active()->stores()
            ->when($user->isSalesAgent(), fn ($q) => $q->whereKey($user->warehouse_id))
            ->orderBy('id')->get();
    }

    private function resolveStore(User $user, int $requested): Warehouse
    {
        $stores = $this->storesFor($user);
        $store = $requested ? $stores->firstWhere('id', $requested) : $stores->first();

        // Un vendeur qui force un autre emplacement dans la requête est refusé.
        abort_if($store === null, 403, 'Vous ne pouvez pas vendre depuis cet emplacement.');

        return $store;
    }

    private function loaded(Sale $sale): Sale
    {
        return $sale->load(['warehouse', 'user', 'cancelledBy', 'items.product']);
    }
}
