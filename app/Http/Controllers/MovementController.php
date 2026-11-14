<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Http\Requests\MovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Historique des mouvements, sorties internes et ajustements manuels. */
class MovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $movements = self::filteredQuery($filters)
            ->with(['product:id,name,sku', 'warehouse:id,name', 'user:id,name'])
            ->latest('id')
            ->paginate($this->perPage() * 2)
            ->withQueryString();

        return $this->listView($request, 'movements.index', 'movements._table', [
            'movements' => $movements,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('id')->pluck('name', 'id'),
            'users' => User::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'types' => collect(MovementType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]),
            'product' => isset($filters['product_id']) ? Product::withTrashed()->find($filters['product_id']) : null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('movements.create', [
            'warehouses' => Warehouse::active()->orderBy('id')->pluck('name', 'id'),
            'product' => $request->integer('product_id') ? Product::active()->find($request->integer('product_id')) : null,
        ]);
    }

    public function store(MovementRequest $request, StockService $stock, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        [$type, $out] = match ($data['kind']) {
            'internal_use' => [MovementType::InternalUse, true],
            'adjustment_out' => [MovementType::ManualAdjustment, true],
            'adjustment_in' => [MovementType::ManualAdjustment, false],
        };

        $movement = DB::transaction(function () use ($stock, $audit, $data, $user, $type, $out) {
            $method = $out ? 'decrease' : 'increase';
            $movement = $stock->$method((int) $data['product_id'], (int) $data['warehouse_id'], (int) $data['quantity'], $type, $user, $data['reason']);

            $audit->record(AuditAction::Created, $movement, sprintf(
                '%s %s : %s %d, motif « %s »', $type->label(), $movement->reference, $out ? '-' : '+', $movement->quantity, $data['reason']
            ), ['stock' => $movement->stock_before], ['stock' => $movement->stock_after], $user->id);

            return $movement;
        });

        return redirect()->route('movements.index', ['product_id' => $movement->product_id])
            ->with('success', "Mouvement {$movement->reference} enregistré : stock passé de {$movement->stock_before} à {$movement->stock_after}.");
    }

    /** Filtres partagés par l'historique et les exports. */
    public function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:'.implode(',', MovementType::values())],
            'direction' => ['nullable', 'in:in,out'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    public static function filteredQuery(array $filters): Builder
    {
        return StockMovement::query()
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('reference', 'like', addcslashes($term, '\\%_').'%')
                    ->orWhereHas('product', fn ($p) => $p->withTrashed()->search($term)));
            })
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['direction'] ?? null, fn ($q, $d) => $q->where('direction', $d))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'));
    }
}
