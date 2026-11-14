<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user();

        if (! $user->can('dashboard.full')) {
            // Variante réduite du vendeur : ses ventes du jour et les ruptures.
            return view('dashboard.agent', [
                'kpis' => $dashboard->kpis($user),
                'latestSales' => Sale::where('user_id', $user->id)->latest('id')->limit(8)->get(),
            ]);
        }

        return view('dashboard.index', [
            'kpis' => $dashboard->kpis($user),
            'byWarehouse' => $dashboard->byWarehouse(),
            'movements' => $dashboard->latestMovements(),
            'toReorder' => $dashboard->toReorder(),
            'refreshSeconds' => (int) config('stockflow.dashboard_refresh_seconds', 30),
        ]);
    }
}
