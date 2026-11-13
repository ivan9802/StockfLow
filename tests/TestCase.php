<?php

namespace Tests;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Entrepôt et boutique, comme dans l'entreprise réelle. */
    protected function createWarehouses(): array
    {
        return [
            Warehouse::factory()->create(['code' => 'ENT', 'name' => 'Entrepôt central']),
            Warehouse::factory()->pointOfSale()->create(['code' => 'PDV', 'name' => 'Point de vente']),
        ];
    }

    /** Met un produit en stock en passant par le service, comme le ferait une réception. */
    protected function stock(Product $product, Warehouse $warehouse, int $quantity, ?User $user = null): void
    {
        if ($quantity === 0) {
            return;
        }

        app(StockService::class)->increase(
            $product->id, $warehouse->id, $quantity, MovementType::Purchase,
            $user ?? User::factory()->create(), null, null, 'BR-TEST-000001'
        );
    }

    protected function stockOf(Product $product, Warehouse $warehouse): int
    {
        return app(StockService::class)->available($product->id, $warehouse->id);
    }
}
