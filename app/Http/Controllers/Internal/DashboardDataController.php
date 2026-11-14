<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Indicateurs du tableau de bord, rafraîchis par Fetch à intervalle régulier. */
class DashboardDataController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $kpis = $dashboard->kpis($request->user());

        // Valeurs déjà formatées pour l'affichage, en plus des valeurs brutes.
        $formatted = collect($kpis)->map(fn ($value, $key) => str_contains($key, 'value') || str_contains($key, 'amount')
            ? Format::money($value) : Format::number($value));

        return response()->json([
            'kpis' => $kpis,
            'formatted' => $formatted,
            'alerts' => $request->user()->can('alerts.view') ? $request->user()->unreadNotifications()->count() : 0,
            'updated_at' => now()->format('H:i:s'),
        ]);
    }
}
