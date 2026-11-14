<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Concerns\NormalizesLines;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Commandes fournisseurs et réceptions.
 *
 * Une commande se reçoit en une ou plusieurs livraisons ; chaque livraison est un
 * bon de réception qui augmente le stock de l'emplacement de livraison.
 * Transitions : draft → ordered → partially_received → received ;
 * draft ou ordered → cancelled tant que rien n'est reçu ; partially_received → closed.
 */
final class PurchaseService
{
    use NormalizesLines;

    public function __construct(
        private readonly StockService $stock,
        private readonly ReferenceGenerator $references,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{supplier_id: int, warehouse_id: int, expected_at?: ?string, notes?: ?string, lines: array}  $data
     */
    public function createDraft(array $data, User $user): PurchaseOrder
    {
        $lines = $this->normalizeLines($data['lines'], ['unit_cost']);
        $supplier = $this->activeSupplier($data['supplier_id']);

        return DB::transaction(function () use ($data, $lines, $supplier, $user) {
            $this->activeProducts($lines);

            $order = PurchaseOrder::create([
                'reference' => $this->references->next(DocumentType::PurchaseOrder),
                'supplier_id' => $supplier->id,
                'warehouse_id' => Warehouse::active()->findOrFail($data['warehouse_id'])->id,
                'user_id' => $user->id,
                'status' => PurchaseOrderStatus::Draft,
                'expected_at' => $data['expected_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->writeItems($order, $lines);

            $this->audit->record(AuditAction::Created, $order, "Commande {$order->reference} créée (brouillon)", [], [], $user->id);

            return $order;
        });
    }

    public function updateDraft(PurchaseOrder $order, array $data, User $user): PurchaseOrder
    {
        $lines = $this->normalizeLines($data['lines'], ['unit_cost']);
        $supplier = $this->activeSupplier($data['supplier_id']);

        return DB::transaction(function () use ($order, $data, $lines, $supplier, $user) {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, [PurchaseOrderStatus::Draft], 'modifiée');
            $this->activeProducts($lines);

            $locked->update([
                'supplier_id' => $supplier->id,
                'warehouse_id' => Warehouse::active()->findOrFail($data['warehouse_id'])->id,
                'expected_at' => $data['expected_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->items()->delete();
            $this->writeItems($locked, $lines);

            $this->audit->record(AuditAction::Updated, $locked, "Commande {$locked->reference} modifiée", [], [], $user->id);

            return $locked;
        });
    }

    /** Passe la commande au fournisseur. La date prévue découle du délai moyen s'il n'y en a pas. */
    public function confirm(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user) {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, [PurchaseOrderStatus::Draft], 'passée');

            $locked->update([
                'status' => PurchaseOrderStatus::Ordered,
                'ordered_at' => today(),
                'expected_at' => $locked->expected_at ?? today()->addDays($locked->supplier->lead_time_days),
            ]);

            $this->audit->record(AuditAction::Validated, $locked, "Commande {$locked->reference} passée au fournisseur",
                ['status' => 'draft'], ['status' => 'ordered'], $user->id);

            return $locked;
        });
    }

    public function cancel(PurchaseOrder $order, User $user, ?string $reason): PurchaseOrder
    {
        $reason = $this->requireReason($reason, 'l\'annulation d\'une commande');

        return DB::transaction(function () use ($order, $user, $reason) {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], 'annulée');

            if ($locked->items()->where('quantity_received', '>', 0)->exists()) {
                throw new InvalidStateTransitionException('Une commande dont une partie a été reçue ne s\'annule pas : soldez-la.');
            }

            $old = $locked->status->value;
            $locked->update(['status' => PurchaseOrderStatus::Cancelled, 'closure_reason' => $reason]);
            $this->audit->record(AuditAction::Cancelled, $locked, "Annulation de la commande {$locked->reference} : $reason",
                ['status' => $old], ['status' => 'cancelled'], $user->id);

            return $locked;
        });
    }

    /** Solde une commande partiellement reçue dont le reliquat ne sera pas livré (D-018). */
    public function close(PurchaseOrder $order, User $user, ?string $reason): PurchaseOrder
    {
        $reason = $this->requireReason($reason, 'le solde d\'une commande');

        return DB::transaction(function () use ($order, $user, $reason) {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, [PurchaseOrderStatus::PartiallyReceived], 'soldée');

            $locked->update(['status' => PurchaseOrderStatus::Closed, 'closure_reason' => $reason]);
            $this->audit->record(AuditAction::Updated, $locked, "Commande {$locked->reference} soldée : $reason",
                ['status' => 'partially_received'], ['status' => 'closed'], $user->id);

            return $locked;
        });
    }

    /**
     * Réception totale ou partielle d'une commande.
     *
     * @param  array<int, array{purchase_order_item_id: int, quantity: int}>  $lines  lignes à zéro ignorées
     * @param  array{delivery_note_number?: ?string, notes?: ?string, received_at?: ?string}  $receiptData
     */
    public function receive(PurchaseOrder $order, array $lines, array $receiptData, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $lines, $receiptData, $user) {
            $locked = $this->lockOrder($order);

            if (! $locked->status->canReceive()) {
                throw new InvalidStateTransitionException(
                    "La commande {$locked->reference} est « {$locked->status->label()} » : elle ne peut pas être réceptionnée."
                );
            }

            /** @var Collection<int, PurchaseOrderItem> $items */
            $items = $locked->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $received = [];
            foreach ($lines as $line) {
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($quantity === 0) {
                    continue;
                }
                $item = $items->get((int) ($line['purchase_order_item_id'] ?? 0));
                if ($item === null || $quantity < 0) {
                    throw new BusinessException('Une ligne de réception ne correspond pas à cette commande.');
                }
                if ($quantity > $item->remaining()) {
                    throw new BusinessException(sprintf(
                        'Réception refusée pour %s : %d attendu%s, %d saisi%s. Un excédent se saisit en réception directe.',
                        $item->product->name, $item->remaining(), $item->remaining() > 1 ? 's' : '', $quantity, $quantity > 1 ? 's' : ''
                    ));
                }
                $received[$item->id] = ['item' => $item, 'quantity' => $quantity];
            }

            if ($received === []) {
                throw new BusinessException('Saisissez au moins une quantité reçue.');
            }

            $reference = $this->references->next(DocumentType::GoodsReceipt);
            $this->stock->lockLevels(array_map(fn ($r) => [$r['item']->product_id, $locked->warehouse_id], $received));

            $receipt = GoodsReceipt::create([
                'reference' => $reference,
                'purchase_order_id' => $locked->id,
                'supplier_id' => $locked->supplier_id,
                'warehouse_id' => $locked->warehouse_id,
                'user_id' => $user->id,
                'delivery_note_number' => $receiptData['delivery_note_number'] ?? null,
                'notes' => $receiptData['notes'] ?? null,
                'received_at' => $this->receivedAt($receiptData),
            ]);

            foreach ($received as ['item' => $item, 'quantity' => $quantity]) {
                $receipt->items()->create([
                    'product_id' => $item->product_id,
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ]);
                $item->increment('quantity_received', $quantity);
                $this->stock->increase($item->product_id, $locked->warehouse_id, $quantity, MovementType::Purchase, $user, null, $receipt, $reference);
            }

            $complete = $locked->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
            $old = $locked->status->value;
            $locked->update(['status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived]);

            $this->audit->record(AuditAction::Created, $receipt, "Réception $reference sur la commande {$locked->reference}",
                ['status' => $old], ['status' => $locked->status->value], $user->id);

            return $receipt;
        }, 3);
    }

    /**
     * Entrée directe, sans commande préalable (D-016).
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_cost: int}>  $lines
     */
    public function receiveDirect(Supplier $supplier, Warehouse $warehouse, array $lines, array $receiptData, User $user): GoodsReceipt
    {
        $lines = $this->normalizeLines($lines, ['unit_cost']);

        if (! $warehouse->is_active) {
            throw new BusinessException('L\'emplacement de réception est inactif.');
        }

        return DB::transaction(function () use ($supplier, $warehouse, $lines, $receiptData, $user) {
            $this->activeProducts($lines);
            $reference = $this->references->next(DocumentType::GoodsReceipt);
            $this->stock->lockLevels(array_map(fn ($l) => [$l['product_id'], $warehouse->id], $lines));

            $receipt = GoodsReceipt::create([
                'reference' => $reference,
                'purchase_order_id' => null,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user->id,
                'delivery_note_number' => $receiptData['delivery_note_number'] ?? null,
                'notes' => $receiptData['notes'] ?? null,
                'received_at' => $this->receivedAt($receiptData),
            ]);

            foreach ($lines as $line) {
                $receipt->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => max(0, (int) $line['unit_cost']),
                ]);
                $this->stock->increase($line['product_id'], $warehouse->id, $line['quantity'], MovementType::Purchase, $user, null, $receipt, $reference);
            }

            $this->audit->record(AuditAction::Created, $receipt, "Réception directe $reference ({$supplier->name})", [], [], $user->id);

            return $receipt;
        }, 3);
    }

    private function writeItems(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product_id'],
                'quantity_ordered' => $line['quantity'],
                'unit_cost' => max(0, (int) $line['unit_cost']),
            ]);
        }
    }

    private function activeSupplier(int $id): Supplier
    {
        $supplier = Supplier::find($id);

        if ($supplier === null || ! $supplier->is_active) {
            throw new BusinessException('Le fournisseur choisi est inactif ou n\'existe plus.');
        }

        return $supplier;
    }

    private function lockOrder(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<int, PurchaseOrderStatus> $allowed */
    private function assertStatus(PurchaseOrder $order, array $allowed, string $verb): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new InvalidStateTransitionException(
                "La commande {$order->reference} est « {$order->status->label()} » : elle ne peut pas être $verb."
            );
        }
    }

    private function receivedAt(array $data): Carbon
    {
        $date = filled($data['received_at'] ?? null) ? Carbon::parse($data['received_at']) : now();

        // Une date du jour reçoit l'heure courante ; une date passée garde midi.
        return $date->isToday() ? now() : $date->setTime(12, 0);
    }
}
