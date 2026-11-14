<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'in:active,inactive'],
        ]);

        $suppliers = Supplier::query()
            ->withCount('products')
            ->when($filters['q'] ?? null, function ($query, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_name', 'like', $like));
            })
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->where('is_active', $state === 'active'))
            ->orderBy('name')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'suppliers.index', 'suppliers._table', compact('suppliers', 'filters'));
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'lead_time_days' => 7])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.index')->with('success', "Le fournisseur {$supplier->name} a été créé.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('success', "Le fournisseur {$supplier->name} a été modifié.");
    }

    /** Suppression logique : les commandes et réceptions passées gardent leur fournisseur. */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', "Le fournisseur {$supplier->name} a été supprimé.");
    }
}
