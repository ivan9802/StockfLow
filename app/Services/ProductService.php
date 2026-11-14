<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Création, modification et retrait d'un produit du catalogue. */
final class ProductService
{
    /** Disque privé : les images ne sont servies qu'à un utilisateur connecté. */
    public const IMAGE_DISK = 'local';

    public const IMAGE_DIRECTORY = 'products';

    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly BarcodeGenerator $barcodes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  données validées par ProductRequest
     */
    public function create(array $data, ?UploadedFile $image = null): Product
    {
        $path = $image ? $this->storeImage($image) : null;

        try {
            return DB::transaction(function () use ($data, $path) {
                $category = Category::findOrFail($data['category_id']);

                // La référence interne est attribuée ici, jamais saisie : préfixe de la
                // catégorie et numéro d'ordre tiré du compteur (D-017).
                $sku = sprintf('%s-%06d', $category->code, $this->references->nextNumber($category->code));
                $barcode = filled($data['barcode'] ?? null) ? $data['barcode'] : $this->barcodes->next();

                $product = Product::create([
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'image_path' => $path,
                ] + $this->attributes($data));

                $this->saveReorderPoints($product, $data['reorder_points'] ?? []);

                return $product;
            });
        } catch (\Throwable $e) {
            // La transaction est annulée : on ne laisse pas d'image orpheline.
            if ($path) {
                Storage::disk(self::IMAGE_DISK)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  données validées par ProductRequest
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        $newPath = $image ? $this->storeImage($image) : null;
        $oldPath = $product->image_path;

        try {
            DB::transaction(function () use ($product, $data, $newPath, $removeImage) {
                $attributes = $this->attributes($data);

                // La référence ne change pas, même si le produit change de catégorie.
                if (filled($data['barcode'] ?? null)) {
                    $attributes['barcode'] = $data['barcode'];
                }
                if ($newPath) {
                    $attributes['image_path'] = $newPath;
                } elseif ($removeImage) {
                    $attributes['image_path'] = null;
                }

                $product->update($attributes);
                $this->saveReorderPoints($product, $data['reorder_points'] ?? []);
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk(self::IMAGE_DISK)->delete($newPath);
            }
            throw $e;
        }

        if ($oldPath && ($newPath || $removeImage)) {
            Storage::disk(self::IMAGE_DISK)->delete($oldPath);
        }

        return $product;
    }

    /**
     * Retire un produit du catalogue (suppression logique). Son historique reste lisible.
     *
     * @throws BusinessException tant qu'il reste du stock
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $remaining = (int) StockLevel::where('product_id', $product->id)->lockForUpdate()->sum('quantity');

            if ($remaining > 0) {
                throw new BusinessException(sprintf(
                    'Le produit %s a encore %d unité%s en stock. Sortez ou transférez ce stock avant de le supprimer, ou désactivez le produit.',
                    $product->sku, $remaining, $remaining > 1 ? 's' : ''
                ));
            }

            $product->delete();
        });
    }

    /** Champs modifiables de la fiche. */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'purchase_price' => (int) $data['purchase_price'],
            'selling_price' => (int) $data['selling_price'],
            'alert_threshold' => (int) ($data['alert_threshold'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** Seuils de réassort par emplacement. Ce ne sont pas des quantités de stock. */
    private function saveReorderPoints(Product $product, array $points): void
    {
        foreach ($points as $warehouseId => $value) {
            StockLevel::where('product_id', $product->id)
                ->where('warehouse_id', (int) $warehouseId)
                ->update(['reorder_point' => ($value === null || $value === '') ? null : (int) $value]);
        }
    }

    /** Enregistre l'image sous un nom régénéré : le nom d'origine n'est jamais repris. */
    private function storeImage(UploadedFile $image): string
    {
        return $image->store(self::IMAGE_DIRECTORY, self::IMAGE_DISK);
    }
}
