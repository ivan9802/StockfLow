<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Events\StockLevelChanged;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MissingReasonException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Seul point d'écriture de stock_levels.quantity.
 *
 * Chaque variation passe par increase() ou decrease(), s'exécute dans une
 * transaction, verrouille la ligne de stock concernée et produit exactement
 * une ligne dans stock_movements avec le stock avant et le stock après.
 * Aucun contrôleur, modèle ou seeder ne modifie une quantité autrement.
 */
final class StockService
{
    public function __construct(private readonly ReferenceGenerator $references) {}

    /** Entrée de stock. */
    public function increase(
        int $productId, int $warehouseId, int $quantity,
        MovementType $type, User $user,
        ?string $reason = null, ?Model $source = null, ?string $reference = null,
    ): StockMovement {
        return $this->move(MovementDirection::In, $productId, $warehouseId, $quantity, $type, $user, $reason, $source, $reference);
    }

    /**
     * Sortie de stock.
     *
     * @throws InsufficientStockException si la quantité dépasse le disponible
     */
    public function decrease(
        int $productId, int $warehouseId, int $quantity,
        MovementType $type, User $user,
        ?string $reason = null, ?Model $source = null, ?string $reference = null,
    ): StockMovement {
        return $this->move(MovementDirection::Out, $productId, $warehouseId, $quantity, $type, $user, $reason, $source, $reference);
    }

    /**
     * Verrouille les lignes de stock d'une opération, toujours dans l'ordre
     * croissant de (product_id, warehouse_id). Deux opérations qui partagent
     * des produits prennent ainsi leurs verrous dans le même ordre et ne
     * peuvent pas se bloquer mutuellement.
     *
     * @param  array<int, array{0: int, 1: int}>  $pairs  couples [product_id, warehouse_id]
     * @return Collection<string, StockLevel> indexée par "product_id:warehouse_id"
     */
    public function lockLevels(array $pairs): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('lockLevels() doit être appelé dans une transaction.');
        }

        $pairs = collect($pairs)
            ->map(fn (array $pair) => [(int) $pair[0], (int) $pair[1]])
            ->unique(fn (array $pair) => $pair[0].':'.$pair[1])
            ->sort(fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $levels = $this->fetchLocked($pairs);

        if ($levels->count() < $pairs->count()) {
            // Ligne absente : produit créé avant l'ajout d'un emplacement, par exemple.
            // On la crée à zéro puis on reprend les verrous.
            $now = now();
            StockLevel::query()->insertOrIgnore(
                $pairs->reject(fn (array $pair) => $levels->has($pair[0].':'.$pair[1]))
                    ->map(fn (array $pair) => [
                        'product_id' => $pair[0], 'warehouse_id' => $pair[1],
                        'quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
                    ])->all()
            );
            $levels = $this->fetchLocked($pairs);
        }

        return $levels;
    }

    /**
     * Contrôle groupé des disponibilités sur des lignes déjà verrouillées.
     * Toutes les lignes sont vérifiées avant de refuser, pour que le message
     * liste chaque manque et pas seulement le premier.
     *
     * @param  Collection<string, StockLevel>  $lockedLevels
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     *
     * @throws InsufficientStockException
     */
    public function assertAvailable(Collection $lockedLevels, array $lines, int $warehouseId): void
    {
        $requested = [];
        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $requested[$productId] = ($requested[$productId] ?? 0) + (int) $line['quantity'];
        }

        $missing = [];
        foreach ($requested as $productId => $quantity) {
            $available = $lockedLevels->get($productId.':'.$warehouseId)?->quantity ?? 0;
            if ($quantity > $available) {
                $missing[$productId] = ['requested' => $quantity, 'available' => $available];
            }
        }

        if ($missing !== []) {
            throw new InsufficientStockException($this->describeShortages($missing, $warehouseId));
        }
    }

    /** Crée à zéro les lignes de stock d'un nouveau produit ou d'un nouvel emplacement. */
    public function initializeLevels(Product|Warehouse $subject): void
    {
        $now = now();

        $rows = $subject instanceof Product
            ? Warehouse::query()->pluck('id')->map(fn ($id) => ['product_id' => $subject->id, 'warehouse_id' => $id])
            : Product::withTrashed()->pluck('id')->map(fn ($id) => ['product_id' => $id, 'warehouse_id' => $subject->id]);

        if ($rows->isEmpty()) {
            return;
        }

        StockLevel::query()->insertOrIgnore(
            $rows->map(fn (array $row) => $row + ['quantity' => 0, 'created_at' => $now, 'updated_at' => $now])->all()
        );
    }

    /** Quantité disponible, lue sans verrou : pour l'affichage uniquement. */
    public function available(int $productId, int $warehouseId): int
    {
        return (int) StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    /** Stock total d'un produit, tous emplacements confondus, lu sans verrou. */
    public function totalFor(int $productId): int
    {
        return (int) StockLevel::query()->where('product_id', $productId)->sum('quantity');
    }

    private function move(
        MovementDirection $direction, int $productId, int $warehouseId, int $quantity,
        MovementType $type, User $user, ?string $reason, ?Model $source, ?string $reference,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La quantité d\'un mouvement doit être strictement positive.');
        }

        if (! in_array($direction, $type->allowedDirections(), true)) {
            throw new InvalidArgumentException(
                sprintf('Le type de mouvement "%s" n\'existe pas dans le sens "%s".', $type->value, $direction->value)
            );
        }

        $reason = $reason !== null ? trim($reason) : null;
        if ($type->requiresReason() && ($reason === null || $reason === '')) {
            throw new MissingReasonException('Un motif est obligatoire pour ce type de mouvement ('.$type->label().').');
        }

        return DB::transaction(function () use ($direction, $productId, $warehouseId, $quantity, $type, $user, $reason, $source, $reference) {
            // Mouvement manuel sans document : il reçoit son propre numéro de bon.
            // Le compteur est verrouillé avant la ligne de stock (ordre constant).
            $reference ??= $this->references->next(DocumentType::ManualMovement);

            // Lecture sous verrou : une opération concurrente sur la même ligne
            // attend ici la fin de cette transaction et lit ensuite la quantité à jour.
            $level = $this->lockLevels([[$productId, $warehouseId]])->first();
            $before = $level->quantity;

            if ($direction === MovementDirection::Out && $quantity > $before) {
                throw new InsufficientStockException($this->describeShortages(
                    [$productId => ['requested' => $quantity, 'available' => $before]], $warehouseId
                ));
            }

            $after = $before + $direction->sign() * $quantity;

            $level->quantity = $after;
            $level->save();

            $movement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'user_id' => $user->id,
                'type' => $type,
                'direction' => $direction,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
                'reference' => $reference,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);

            // Livré après le commit seulement : sert aux alertes de seuil.
            StockLevelChanged::dispatch($movement);

            return $movement;
        });
    }

    /** @param Collection<int, array{0: int, 1: int}> $pairs */
    private function fetchLocked(Collection $pairs): Collection
    {
        return StockLevel::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as [$productId, $warehouseId]) {
                    $query->orWhere(fn ($q) => $q->where('product_id', $productId)->where('warehouse_id', $warehouseId));
                }
            })
            ->orderBy('product_id')
            ->orderBy('warehouse_id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (StockLevel $level) => $level->product_id.':'.$level->warehouse_id);
    }

    /**
     * @param  array<int, array{requested: int, available: int}>  $missing  indexé par product_id
     * @return array<int, array{product_id: int, product_name: string, sku: string, warehouse_id: int, requested: int, available: int}>
     */
    private function describeShortages(array $missing, int $warehouseId): array
    {
        $products = Product::withTrashed()->whereIn('id', array_keys($missing))->get(['id', 'name', 'sku'])->keyBy('id');

        $shortages = [];
        foreach ($missing as $productId => $figures) {
            $shortages[] = [
                'product_id' => $productId,
                'product_name' => $products[$productId]->name ?? 'Produit n° '.$productId,
                'sku' => $products[$productId]->sku ?? '',
                'warehouse_id' => $warehouseId,
                'requested' => $figures['requested'],
                'available' => $figures['available'],
            ];
        }

        return $shortages;
    }
}
