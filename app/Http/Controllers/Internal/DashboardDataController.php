<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardPeriodRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

/**
 * Données du tableau de bord pour une période, appelées par Fetch quand
 * l'utilisateur change de période ou lors du rafraîchissement automatique.
 */
class DashboardDataController extends Controller
{
    public function __invoke(DashboardPeriodRequest $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();

        return response()->json($dashboard->forPeriod($request->period(), $user, $request->wantsComparison()) + [
            'alerts' => $user->can('alerts.view') ? $user->unreadNotifications()->count() : 0,
            'updated_at' => now()->format('H:i'),
        ]);
    }
}
