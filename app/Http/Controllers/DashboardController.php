<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardPeriodRequest;
use App\Services\DashboardService;
use App\Support\Period;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Le tableau de bord est rendu complet par le serveur (lisible sans JavaScript),
     * puis actualisé par Fetch à chaque changement de période.
     */
    public function index(DashboardPeriodRequest $request, DashboardService $dashboard): View
    {
        $user = $request->user();
        $period = $request->period();

        return view('dashboard.index', [
            'data' => $dashboard->forPeriod($period, $user, $request->wantsComparison()),
            'presets' => Period::PRESETS,
            'full' => $user->can('dashboard.full'),
            'movements' => $user->can('movements.view') ? $dashboard->latestMovements() : collect(),
            'sales' => $dashboard->latestSales($user),
            'toReorder' => $user->can('alerts.view') ? $dashboard->toReorder() : collect(),
            'notifications' => $user->can('alerts.view') ? $user->notifications()->latest()->limit(5)->get() : collect(),
            'refreshSeconds' => (int) config('stockflow.dashboard_refresh_seconds', 30),
        ]);
    }
}
