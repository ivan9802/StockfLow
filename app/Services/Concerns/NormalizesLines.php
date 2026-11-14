<?php

namespace App\Services\Concerns;

use App\Exceptions\BusinessException;
use App\Exceptions\MissingReasonException;
use App\Models\Product;
use Illuminate\Support\Collection;

/** Outils communs aux services qui manipulent des lignes de document. */
trait NormalizesLines
{
    /**
     * Regroupe les lignes par produit (un produit saisi deux fois devient une ligne)
     * et les trie par product_id, l'ordre de verrouillage commun.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{product_id: int, quantity: int}>
     */
    protected function normalizeLines(array $lines, array $extraKeys = []): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new BusinessException('Chaque ligne doit porter un produit et une quantité supérieure à zéro.');
            }

            if (! isset($merged[$productId])) {
                $merged[$productId] = ['product_id' => $productId, 'quantity' => 0];
                foreach ($extraKeys as $key) {
                    $merged[$productId][$key] = $line[$key] ?? null;
                }
            }
            $merged[$productId]['quantity'] += $quantity;
        }

        if ($merged === []) {
            throw new BusinessException('Ajoutez au moins un produit.');
        }

        ksort($merged);

        return array_values($merged);
    }

    /**
     * Produits des lignes, indexés par id. Refuse un produit inconnu, supprimé ou inactif.
     *
     * @return Collection<int, Product>
     */
    protected function activeProducts(array $lines): Collection
    {
        $ids = array_column($lines, 'product_id');
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $product = $products->get($id);
            if ($product === null) {
                throw new BusinessException('Un des produits demandés n\'existe plus dans le catalogue.');
            }
            if (! $product->is_active) {
                throw new BusinessException("Le produit {$product->name} est inactif : il ne peut plus être vendu, commandé ni transféré.");
            }
        }

        return $products;
    }

    /** Motif obligatoire d'au moins 5 caractères utiles. */
    protected function requireReason(?string $reason, string $what = 'cette opération'): string
    {
        $reason = trim((string) $reason);

        if (mb_strlen($reason) < 5) {
            throw new MissingReasonException("Un motif d'au moins 5 caractères est obligatoire pour $what.");
        }

        return mb_substr($reason, 0, 500);
    }
}
