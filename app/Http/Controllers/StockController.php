<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** État des stocks : une ligne par produit, une colonne par emplacement. */
class StockController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:ok,low,out'],
        ]);

        $warehouses = Warehouse::active()->orderBy('id')->get();

        $products = Product::query()
            ->active()
            ->with(['category:id,name', 'stockLevels:id,product_id,warehouse_id,quantity,reorder_point'])
            ->withTotalStock()
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->stockStatus($filters['stock'] ?? null)
            ->orderBy('name')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'stock.index', 'stock._table', [
            'products' => $products,
            'warehouses' => $warehouses,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'showValue' => $request->user()->can('prices.view-purchase'),
        ]);
    }
}
