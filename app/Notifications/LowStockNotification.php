<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Notifications\Notification;

/** Alerte affichée dans l'interface (cloche et centre d'alertes). Canal base de données uniquement. */
class LowStockNotification extends Notification
{
    public function __construct(
        private readonly Product $product,
        private readonly ?int $warehouseId,
        private readonly int $quantity,
        private readonly int $threshold,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $warehouse = $this->warehouseId ? Warehouse::find($this->warehouseId)?->name : null;

        return [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseId,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'quantity' => $this->quantity,
            'threshold' => $this->threshold,
            'message' => sprintf(
                '%s %s : %d en stock%s, seuil %d.',
                $this->quantity === 0 ? 'Rupture de' : 'Stock bas pour',
                $this->product->name,
                $this->quantity,
                $warehouse ? " ($warehouse)" : ' au total',
                $this->threshold,
            ),
        ];
    }
}
