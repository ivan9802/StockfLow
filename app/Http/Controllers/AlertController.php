<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centre d'alertes : produits à réapprovisionner et notifications reçues. */
class AlertController extends Controller
{
    public function index(Request $request, AlertService $alerts): View
    {
        return view('alerts.index', [
            'products' => $alerts->productsBelowThreshold(),
            'notifications' => $request->user()->notifications()->latest()->limit(50)->get(),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Les alertes ont été marquées comme lues.');
    }
}
