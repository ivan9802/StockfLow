<?php

namespace App\Services;

use App\Enums\MovementDirection;
use App\Enums\Role;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Alertes de réapprovisionnement (D-008, D-013).
 *
 * Deux seuils : celui du produit, comparé au stock total ; celui d'un emplacement
 * (reorder_point), comparé au stock de cet emplacement. Une notification n'est
 * créée qu'au franchissement, quand la quantité passe d'au-dessus du seuil à
 * au plus le seuil (règle du cahier : quantité <= seuil).
 */
final class AlertService
{
    public function evaluate(StockMovement $movement): void
    {
        if ($movement->direction !== MovementDirection::Out) {
            return;
        }

        $product = Product::withTrashed()->find($movement->product_id);
        if ($product === null || $product->trashed() || ! $product->is_active) {
            return;
        }

        // Seuil du produit, sur le stock total.
        $totalAfter = (int) StockLevel::where('product_id', $product->id)->sum('quantity');
        $totalBefore = $totalAfter + $movement->quantity;
        if ($totalBefore > $product->alert_threshold && $totalAfter <= $product->alert_threshold) {
            $this->notify($product, null, $totalAfter, $product->alert_threshold);
        }

        // Seuil de l'emplacement, s'il est renseigné.
        $reorderPoint = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $movement->warehouse_id)->value('reorder_point');
        if ($reorderPoint !== null && $movement->stock_before > $reorderPoint && $movement->stock_after <= $reorderPoint) {
            $this->notify($product, $movement->warehouse_id, $movement->stock_after, (int) $reorderPoint);
        }
    }

    /** Produits actifs dont le stock total est au plus leur seuil, ruptures en tête. */
    public function productsBelowThreshold(int $limit = 0): Collection
    {
        return $this->belowThresholdQuery()
            ->with(['category:id,name', 'supplier:id,name,lead_time_days', 'stockLevels:id,product_id,warehouse_id,quantity'])
            ->orderBy('total_stock')->orderBy('name')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();
    }

    public function belowThresholdQuery(): Builder
    {
        return Product::query()->active()->withTotalStock()
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id) <= products.alert_threshold');
    }

    /** Destinataires : administrateurs et gestionnaires actifs. */
    public function recipients(): Collection
    {
        return User::where('is_active', true)
            ->whereIn('role', [Role::Admin->value, Role::StockManager->value])->get();
    }

    private function notify(Product $product, ?int $warehouseId, int $quantity, int $threshold): void
    {
        $recipients = $this->recipients()->reject(function (User $user) use ($product, $warehouseId) {
            // Pas de doublon tant qu'une alerte non lue existe pour le même produit et la même portée.
            return $user->unreadNotifications()
                ->where('type', LowStockNotification::class)
                ->get(['data'])
                ->contains(fn ($n) => ($n->data['product_id'] ?? null) === $product->id
                    && ($n->data['warehouse_id'] ?? null) === $warehouseId);
        });

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LowStockNotification($product, $warehouseId, $quantity, $threshold));
        }
    }
}
