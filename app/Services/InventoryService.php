<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\InventoryStatus;
use App\Enums\MovementType;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\InventoryAlreadyOpenException;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Concerns\NormalizesLines;
use Illuminate\Support\Facades\DB;

/**
 * Inventaires physiques.
 *
 * Les ventes continuent pendant le comptage (D-007). Pour que l'écart soit juste,
 * le stock théorique d'une ligne est relevé au moment où elle est comptée, et non à
 * l'ouverture : une vente faite avant le comptage est déjà dans le théorique, une
 * vente faite après est préservée puisque l'écart s'applique au stock courant.
 *
 * Cycle : in_progress → pending_validation → validated, avec retour possible en
 * comptage (sendBack) et annulation tant que rien n'est validé.
 */
final class InventoryService
{
    use NormalizesLines;

    public function __construct(
        private readonly StockService $stock,
        private readonly ReferenceGenerator $references,
        private readonly AuditService $audit,
    ) {}

    public function open(Warehouse $warehouse, ?Category $category, User $user, ?string $notes = null): InventorySession
    {
        return DB::transaction(function () use ($warehouse, $category, $user, $notes) {
            // Le verrou sur l'emplacement sérialise deux ouvertures simultanées.
            Warehouse::whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            $open = InventorySession::where('warehouse_id', $warehouse->id)
                ->whereIn('status', InventoryStatus::openValues())->first();
            if ($open) {
                throw new InventoryAlreadyOpenException(
                    "L'inventaire {$open->reference} est déjà ouvert sur {$warehouse->name}. Terminez-le ou annulez-le d'abord."
                );
            }

            $productIds = Product::query()
                ->when($category, fn ($q) => $q->where('category_id', $category->id))
                ->orderBy('id')->pluck('id');

            if ($productIds->isEmpty()) {
                throw new BusinessException('Aucun produit à compter dans ce périmètre.');
            }

            $session = InventorySession::create([
                'reference' => $this->references->next(DocumentType::Inventory),
                'warehouse_id' => $warehouse->id,
                'category_id' => $category?->id,
                'status' => InventoryStatus::InProgress,
                'started_by' => $user->id,
                'started_at' => now(),
                'notes' => $notes,
            ]);

            InventoryItem::insert($productIds->map(fn ($id) => [
                'inventory_session_id' => $session->id, 'product_id' => $id,
            ])->all());

            $this->audit->record(AuditAction::Created, $session,
                "Ouverture de l'inventaire {$session->reference} sur {$warehouse->name}", [], [], $user->id);

            return $session;
        });
    }

    /**
     * Enregistre des comptages, en une ou plusieurs fois.
     *
     * @param  array<int, array{counted_quantity: ?int, justification?: ?string}>  $counts  indexé par id de ligne
     */
    public function recordCounts(InventorySession $session, array $counts, User $user): InventorySession
    {
        return DB::transaction(function () use ($session, $counts, $user) {
            $locked = $this->lockSession($session);
            $this->assertStatus($locked, [InventoryStatus::InProgress], 'recevoir de comptage');

            $items = $locked->items()->whereIn('id', array_keys($counts))->orderBy('product_id')->get();

            foreach ($items as $item) {
                $count = $counts[$item->id];
                $counted = $count['counted_quantity'] ?? null;

                if ($counted === null || $counted === '') {
                    continue;
                }
                $counted = (int) $counted;
                if ($counted < 0) {
                    throw new BusinessException('Une quantité comptée ne peut pas être négative.');
                }

                // Théorique relevé maintenant, sous verrou de la ligne de stock (D-007).
                $level = $this->stock->lockLevels([[$item->product_id, $locked->warehouse_id]])->first();

                $item->update([
                    'theoretical_quantity' => $level->quantity,
                    'counted_quantity' => $counted,
                    'variance' => $counted - $level->quantity,
                    'counted_at' => now(),
                    'counted_by' => $user->id,
                    'justification' => filled($count['justification'] ?? null) ? mb_substr(trim($count['justification']), 0, 500) : $item->justification,
                ]);
            }

            return $locked;
        });
    }

    /** Fin du comptage : toutes les lignes comptées, chaque écart justifié. */
    public function submit(InventorySession $session, User $user): InventorySession
    {
        return DB::transaction(function () use ($session, $user) {
            $locked = $this->lockSession($session);
            $this->assertStatus($locked, [InventoryStatus::InProgress], 'être soumis');

            $uncounted = $locked->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw new BusinessException("Il reste $uncounted ligne".($uncounted > 1 ? 's' : '').' à compter.');
            }

            $unjustified = $locked->items()->with('product:id,name')
                ->where('variance', '<>', 0)
                ->where(fn ($q) => $q->whereNull('justification')->orWhereRaw('CHAR_LENGTH(TRIM(justification)) < 5'))
                ->get();
            if ($unjustified->isNotEmpty()) {
                throw new BusinessException('Justifiez chaque écart (5 caractères au moins) : '
                    .$unjustified->pluck('product.name')->take(5)->implode(', ').($unjustified->count() > 5 ? '...' : '').'.');
            }

            $locked->update(['status' => InventoryStatus::PendingValidation, 'submitted_at' => now()]);
            $this->audit->record(AuditAction::Updated, $locked, "Inventaire {$locked->reference} soumis à validation",
                ['status' => 'in_progress'], ['status' => 'pending_validation'], $user->id);

            return $locked;
        });
    }

    /** L'administrateur renvoie la session en comptage, avec un commentaire. */
    public function sendBack(InventorySession $session, User $admin, ?string $comment): InventorySession
    {
        $comment = $this->requireReason($comment, 'le renvoi en comptage');

        return DB::transaction(function () use ($session, $admin, $comment) {
            $locked = $this->lockSession($session);
            $this->assertStatus($locked, [InventoryStatus::PendingValidation], 'être renvoyé en comptage');

            $locked->update(['status' => InventoryStatus::InProgress, 'review_comment' => $comment, 'submitted_at' => null]);
            $this->audit->record(AuditAction::Updated, $locked, "Inventaire {$locked->reference} renvoyé en comptage : $comment",
                ['status' => 'pending_validation'], ['status' => 'in_progress'], $admin->id);

            return $locked;
        });
    }

    /**
     * Valide et applique les écarts au stock courant, en une transaction.
     *
     * @throws InsufficientStockException si un écart négatif dépasse le stock courant :
     *                                    rien n'est appliqué, la ligne doit être recomptée
     */
    public function validate(InventorySession $session, User $admin): InventorySession
    {
        return DB::transaction(function () use ($session, $admin) {
            $locked = $this->lockSession($session);
            $this->assertStatus($locked, [InventoryStatus::PendingValidation], 'être validé');

            $items = $locked->items()->where('variance', '<>', 0)->orderBy('product_id')->get();
            $this->stock->lockLevels($items->map(fn ($i) => [$i->product_id, $locked->warehouse_id])->all());

            foreach ($items as $item) {
                $reason = 'Inventaire '.$locked->reference.' : '.$item->justification;
                if ($item->variance > 0) {
                    $this->stock->increase($item->product_id, $locked->warehouse_id, $item->variance, MovementType::InventoryAdjustment, $admin, $reason, $locked, $locked->reference);
                } else {
                    $this->stock->decrease($item->product_id, $locked->warehouse_id, -$item->variance, MovementType::InventoryAdjustment, $admin, $reason, $locked, $locked->reference);
                }
            }

            $locked->update(['status' => InventoryStatus::Validated, 'validated_by' => $admin->id, 'validated_at' => now()]);
            $this->audit->record(AuditAction::Validated, $locked, sprintf(
                'Validation de l\'inventaire %s : %d écart%s régularisé%s', $locked->reference,
                $items->count(), $items->count() > 1 ? 's' : '', $items->count() > 1 ? 's' : ''
            ), ['status' => 'pending_validation'], ['status' => 'validated'], $admin->id);

            return $locked;
        }, 3);
    }

    public function cancel(InventorySession $session, User $user, ?string $reason): InventorySession
    {
        $reason = $this->requireReason($reason, 'l\'annulation d\'un inventaire');

        return DB::transaction(function () use ($session, $user, $reason) {
            $locked = $this->lockSession($session);
            $this->assertStatus($locked, [InventoryStatus::InProgress, InventoryStatus::PendingValidation], 'être annulé');

            $old = $locked->status->value;
            $locked->update(['status' => InventoryStatus::Cancelled, 'cancel_reason' => $reason]);
            $this->audit->record(AuditAction::Cancelled, $locked, "Annulation de l'inventaire {$locked->reference} : $reason",
                ['status' => $old], ['status' => 'cancelled'], $user->id);

            return $locked;
        });
    }

    private function lockSession(InventorySession $session): InventorySession
    {
        return InventorySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<int, InventoryStatus> $allowed */
    private function assertStatus(InventorySession $session, array $allowed, string $what): void
    {
        if (! in_array($session->status, $allowed, true)) {
            throw new InvalidStateTransitionException(
                "L'inventaire {$session->reference} est « {$session->status->label()} » : il ne peut pas $what."
            );
        }
    }
}
