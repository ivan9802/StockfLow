<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Emplacements. Les deux emplacements sont créés par le seeder (D-020) :
 * l'administrateur en modifie le nom et l'adresse, il n'en crée ni n'en supprime.
 */
class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('warehouses.index', [
            'warehouses' => Warehouse::query()->orderBy('id')
                ->withSum('stockLevels as units', 'quantity')
                ->withCount(['stockLevels as references' => fn ($q) => $q->where('quantity', '>', 0)])
                ->get(),
        ]);
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.form', ['warehouse' => $warehouse]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]));

        return redirect()->route('warehouses.index')->with('success', "Emplacement {$warehouse->name} modifié.");
    }
}
