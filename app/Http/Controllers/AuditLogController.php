<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consultation du journal d'audit, réservée à l'administrateur. Lecture seule. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'in:'.implode(',', AuditAction::values())],
            'user_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('description', 'like', '%'.addcslashes($t, '\\%_').'%'))
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['subject'] ?? null, fn ($q, $s) => $q->where('auditable_type', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return $this->listView($request, 'audit-logs.index', 'audit-logs._table', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => collect(AuditAction::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]),
            'users' => User::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'subjects' => [
                'product' => 'Produits', 'category' => 'Catégories', 'supplier' => 'Fournisseurs', 'user' => 'Utilisateurs',
                'warehouse' => 'Emplacements', 'sale' => 'Ventes', 'stock_transfer' => 'Transferts', 'purchase_order' => 'Commandes',
                'goods_receipt' => 'Réceptions', 'inventory_session' => 'Inventaires', 'stock_movement' => 'Mouvements manuels',
            ],
        ]);
    }
}
