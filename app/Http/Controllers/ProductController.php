<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\BarcodeGenerator;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    private const SORTS = ['name', 'sku', 'selling_price', 'total_stock'];

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:ok,low,out'],
            'state' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $products = Product::query()
            ->with('category:id,name')
            ->withTotalStock()
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->where('is_active', $state === 'active'))
            ->stockStatus($filters['stock'] ?? null)
            ->orderBy($filters['sort'] ?? 'name', $filters['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->listView($request, 'products.index', 'products._table', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'suppliers' => $request->user()->can('suppliers.manage') ? Supplier::orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function create(): View
    {
        return view('products.form', ['product' => new Product(['is_active' => true, 'alert_threshold' => 5])] + $this->formData());
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated(), $request->file('image'));

        return redirect()->route('products.show', $product)
            ->with('success', "Le produit {$product->sku} a été créé.");
    }

    public function show(Request $request, Product $product, BarcodeGenerator $barcodes): View
    {
        $product->load(['category', 'supplier', 'stockLevels.warehouse']);

        return view('products.show', [
            'product' => $product,
            'barcodeSvg' => $barcodes->svg($product->barcode),
            // La fiche de stock n'est montrée qu'aux profils qui gèrent le stock.
            'movements' => $request->user()->can('movements.view')
                ? StockMovement::with(['warehouse:id,name', 'user:id,name'])
                    ->where('product_id', $product->id)->latest('id')->limit(15)->get()
                : collect(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.form', ['product' => $product->load('stockLevels')] + $this->formData());
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->validated(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('products.show', $product)
            ->with('success', "Le produit {$product->sku} a été modifié.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('products.index')
            ->with('success', "Le produit {$product->sku} a été retiré du catalogue.");
    }

    /** Image d'un produit, servie seulement à un utilisateur connecté. */
    public function image(Product $product): BinaryFileResponse
    {
        $disk = Storage::disk(ProductService::IMAGE_DISK);
        abort_unless($product->image_path && $disk->exists($product->image_path), 404);

        return response()->file($disk->path($product->image_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function formData(): array
    {
        return [
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('id')->get(),
        ];
    }
}
