<?php

namespace App\View\Composers;

use App\Enums\InventoryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventorySession;
use App\Models\PurchaseOrder;
use Illuminate\View\View;

/** Compteurs affichés dans la barre latérale et la cloche, selon les droits de l'utilisateur. */
class NavigationComposer
{
    private ?array $counts = null;

    public function compose(View $view): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        // Calculés une fois par requête, même si plusieurs vues partielles les demandent.
        $this->counts ??= [
            'alerts' => $user->can('alerts.view') ? $user->unreadNotifications()->count() : 0,
            'inventories' => $user->can('inventories.validate')
                ? InventorySession::where('status', InventoryStatus::PendingValidation->value)->count() : 0,
            'purchases' => $user->can('purchases.manage')
                ? PurchaseOrder::whereIn('status', [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value])->count() : 0,
        ];

        $view->with('navCounts', $this->counts);
    }
}
