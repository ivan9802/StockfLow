<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche instantanée de produit, appelée par Fetch.
 * Renvoie le stock de l'emplacement demandé, ou le stock total sans emplacement.
 * Le prix d'achat n'est jamais renvoyé : ce point est ouvert aux vendeurs.
 */
class ProductSearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? null;

        $products = Product::query()
            ->active()
            ->search($validated['q'])
            ->withSum(['stockLevels as stock' => fn ($q) => $q->when($warehouseId, fn ($w) => $w->where('warehouse_id', $warehouseId))], 'quantity')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'sku', 'barcode', 'name', 'selling_price', 'alert_threshold']);

        return response()->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_price' => $product->selling_price,
                'stock' => (int) $product->stock,
                'url' => route('products.show', $product),
            ]),
        ]);
    }
}
