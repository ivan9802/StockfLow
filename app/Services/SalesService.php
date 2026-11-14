<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Concerns\NormalizesLines;
use Illuminate\Support\Facades\DB;

/**
 * Ventes en boutique.
 *
 * Une vente, ses lignes et ses mouvements sont écrits ensemble ou pas du tout :
 * si une seule ligne manque de stock, tout le panier est refusé (pas de vente partielle).
 */
final class SalesService
{
    use NormalizesLines;

    public function __construct(
        private readonly StockService $stock,
        private readonly ReferenceGenerator $references,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     *
     * @throws InsufficientStockException si une ligne dépasse le disponible
     */
    public function create(Warehouse $warehouse, array $lines, ?string $customerName, User $user): Sale
    {
        if (! $warehouse->is_active || ! $warehouse->isStore()) {
            throw new BusinessException('Une vente ne peut partir que d\'un point de vente actif.');
        }

        $lines = $this->normalizeLines($lines);

        // Trois essais : MySQL peut désigner cette transaction comme victime d'un interblocage.
        return DB::transaction(function () use ($warehouse, $lines, $customerName, $user) {
            // Ordre de verrouillage constant : compteur de références, puis lignes de stock.
            $reference = $this->references->next(DocumentType::Sale);

            $levels = $this->stock->lockLevels(array_map(fn ($l) => [$l['product_id'], $warehouse->id], $lines));

            // Contrôle complet avant toute écriture : le message liste chaque manque.
            $this->stock->assertAvailable($levels, $lines, $warehouse->id);

            // Les prix sont lus en base dans la transaction, jamais repris du formulaire.
            $products = $this->activeProducts($lines);

            $items = array_map(function (array $line) use ($products) {
                $price = $products[$line['product_id']]->selling_price;

                return $line + ['unit_price' => $price, 'line_total' => $price * $line['quantity']];
            }, $lines);

            $sale = Sale::create([
                'reference' => $reference,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user->id,
                'customer_name' => filled($customerName) ? mb_substr(trim($customerName), 0, 150) : null,
                'status' => SaleStatus::Completed,
                'total_amount' => array_sum(array_column($items, 'line_total')),
                'sold_at' => now(),
            ]);

            $sale->items()->createMany($items);

            foreach ($lines as $line) {
                $this->stock->decrease(
                    $line['product_id'], $warehouse->id, $line['quantity'], MovementType::Sale, $user,
                    null, $sale, $reference,
                );
            }

            $this->audit->record(AuditAction::Created, $sale, sprintf(
                'Vente %s : %d article%s, %s F CFA', $reference, array_sum(array_column($lines, 'quantity')),
                array_sum(array_column($lines, 'quantity')) > 1 ? 's' : '', number_format($sale->total_amount, 0, ',', ' ')
            ), [], ['total_amount' => $sale->total_amount, 'warehouse_id' => $warehouse->id], $user->id);

            return $sale;
        }, 3);
    }

    /** Annule une vente : chaque ligne est réintégrée au stock de l'emplacement. */
    public function cancel(Sale $sale, User $user, ?string $reason): Sale
    {
        $reason = $this->requireReason($reason, 'l\'annulation d\'une vente');

        return DB::transaction(function () use ($sale, $user, $reason) {
            /** @var Sale $locked */
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SaleStatus::Completed) {
                throw new InvalidStateTransitionException("La vente {$locked->reference} est déjà annulée.");
            }

            $items = $locked->items()->orderBy('product_id')->get();
            $this->stock->lockLevels($items->map(fn ($i) => [$i->product_id, $locked->warehouse_id])->all());

            foreach ($items as $item) {
                $this->stock->increase(
                    $item->product_id, $locked->warehouse_id, $item->quantity, MovementType::SaleCancellation, $user,
                    $reason, $locked, $locked->reference,
                );
            }

            $locked->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->audit->record(AuditAction::Cancelled, $locked, "Annulation de la vente {$locked->reference} : $reason",
                ['status' => SaleStatus::Completed->value], ['status' => SaleStatus::Cancelled->value], $user->id);

            return $locked;
        }, 3);
    }
}
