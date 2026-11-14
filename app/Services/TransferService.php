<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Concerns\NormalizesLines;
use Illuminate\Support\Facades\DB;

/**
 * Transferts entre emplacements, exécutés en une étape (D-006) : la sortie de la
 * source et l'entrée à destination sont écrites dans la même transaction.
 * Il n'existe pas de stock « en transit » : la somme des deux emplacements ne bouge pas.
 */
final class TransferService
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
     * @throws InsufficientStockException si la source manque de stock
     */
    public function execute(Warehouse $from, Warehouse $to, array $lines, ?string $notes, User $user): StockTransfer
    {
        if ($from->is($to)) {
            throw new BusinessException('L\'emplacement de départ et celui d\'arrivée doivent être différents.');
        }
        if (! $from->is_active || ! $to->is_active) {
            throw new BusinessException('Les deux emplacements doivent être actifs.');
        }

        $lines = $this->normalizeLines($lines);

        return DB::transaction(function () use ($from, $to, $lines, $notes, $user) {
            $this->activeProducts($lines);
            $reference = $this->references->next(DocumentType::Transfer);

            // Les lignes des deux emplacements sont verrouillées ensemble, dans l'ordre commun.
            $pairs = [];
            foreach ($lines as $line) {
                $pairs[] = [$line['product_id'], $from->id];
                $pairs[] = [$line['product_id'], $to->id];
            }
            $levels = $this->stock->lockLevels($pairs);
            $this->stock->assertAvailable($levels, $lines, $from->id);

            $transfer = StockTransfer::create([
                'reference' => $reference,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'user_id' => $user->id,
                'status' => TransferStatus::Completed,
                'notes' => filled($notes) ? mb_substr(trim($notes), 0, 500) : null,
                'transferred_at' => now(),
            ]);
            $transfer->items()->createMany($lines);

            foreach ($lines as $line) {
                $this->stock->decrease($line['product_id'], $from->id, $line['quantity'], MovementType::Transfer, $user, null, $transfer, $reference);
                $this->stock->increase($line['product_id'], $to->id, $line['quantity'], MovementType::Transfer, $user, null, $transfer, $reference);
            }

            $this->audit->record(AuditAction::Created, $transfer,
                "Transfert $reference de {$from->name} vers {$to->name}", [], [], $user->id);

            return $transfer;
        }, 3);
    }

    /**
     * Annule un transfert : la marchandise revient à la source.
     *
     * @throws InsufficientStockException si la destination a déjà vendu ou déplacé une partie des quantités
     */
    public function cancel(StockTransfer $transfer, User $user, ?string $reason): StockTransfer
    {
        $reason = $this->requireReason($reason, 'l\'annulation d\'un transfert');

        return DB::transaction(function () use ($transfer, $user, $reason) {
            /** @var StockTransfer $locked */
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TransferStatus::Completed) {
                throw new InvalidStateTransitionException("Le transfert {$locked->reference} est déjà annulé.");
            }

            $lines = $locked->items()->orderBy('product_id')->get(['product_id', 'quantity'])->toArray();
            $pairs = [];
            foreach ($lines as $line) {
                $pairs[] = [$line['product_id'], $locked->from_warehouse_id];
                $pairs[] = [$line['product_id'], $locked->to_warehouse_id];
            }
            $levels = $this->stock->lockLevels($pairs);
            $this->stock->assertAvailable($levels, $lines, $locked->to_warehouse_id);

            foreach ($lines as $line) {
                $this->stock->decrease($line['product_id'], $locked->to_warehouse_id, $line['quantity'], MovementType::Transfer, $user, $reason, $locked, $locked->reference);
                $this->stock->increase($line['product_id'], $locked->from_warehouse_id, $line['quantity'], MovementType::Transfer, $user, $reason, $locked, $locked->reference);
            }

            $locked->update([
                'status' => TransferStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->audit->record(AuditAction::Cancelled, $locked, "Annulation du transfert {$locked->reference} : $reason",
                ['status' => TransferStatus::Completed->value], ['status' => TransferStatus::Cancelled->value], $user->id);

            return $locked;
        }, 3);
    }
}
