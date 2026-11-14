<?php

namespace Tests\Feature\Stock;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_historique_produit_par_les_services_est_coherent(): void
    {
        [$warehouse, $store] = $this->createWarehouses();
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->stock($product, $warehouse, 20, $user);
        app(TransferService::class)->execute($warehouse, $store, [['product_id' => $product->id, 'quantity' => 8]], null, $user);
        $sale = app(SalesService::class)->create($store, [['product_id' => $product->id, 'quantity' => 3]], null, $user);
        app(SalesService::class)->cancel($sale, $user, 'Annulation de contrôle');
        app(StockService::class)->decrease($product->id, $warehouse->id, 2, MovementType::InternalUse, $user, 'Usage interne');

        $this->artisan('stock:verify')->expectsOutputToContain('aucun écart')->assertSuccessful();
    }

    public function test_une_modification_directe_du_stock_est_detectee(): void
    {
        [$warehouse] = $this->createWarehouses();
        $product = Product::factory()->create();
        $this->stock($product, $warehouse, 5);

        // Contournement du service : exactement ce que la règle interdit.
        DB::table('stock_levels')->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->update(['quantity' => 9]);

        $this->artisan('stock:verify')->expectsOutputToContain('différents de la somme')->assertFailed();
    }
}
