<?php

namespace Tests\Feature\Stock;

use App\Enums\PurchaseOrderStatus;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferAndPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Warehouse $store;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->manager = User::factory()->stockManager()->create();
    }

    // ------------------------------------------------------------ Transferts

    public function test_un_transfert_deplace_le_stock_sans_changer_le_total(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 20);

        $transfer = app(TransferService::class)->execute($this->warehouse, $this->store, [['product_id' => $product->id, 'quantity' => 8]], 'Réassort du lundi', $this->manager);

        $this->assertSame(12, $this->stockOf($product, $this->warehouse));
        $this->assertSame(8, $this->stockOf($product, $this->store));
        $movements = StockMovement::where('reference', $transfer->reference)->get();
        $this->assertCount(2, $movements);
        $this->assertSame(0, $movements->sum(fn ($m) => $m->signedQuantity()));
    }

    public function test_un_transfert_superieur_au_stock_de_la_source_est_refuse(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 3);

        try {
            app(TransferService::class)->execute($this->warehouse, $this->store, [['product_id' => $product->id, 'quantity' => 5]], null, $this->manager);
            $this->fail('Transfert accepté.');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(3, $this->stockOf($product, $this->warehouse));
        $this->assertSame(0, $this->stockOf($product, $this->store));

        $this->expectException(BusinessException::class);
        app(TransferService::class)->execute($this->store, $this->store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->manager);
    }

    public function test_l_annulation_d_un_transfert_ramene_le_stock_sauf_s_il_a_ete_vendu(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 10);
        $service = app(TransferService::class);

        $first = $service->execute($this->warehouse, $this->store, [['product_id' => $product->id, 'quantity' => 4]], null, $this->manager);
        $service->cancel($first, $this->manager, 'Erreur de saisie');
        $this->assertSame(TransferStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(10, $this->stockOf($product, $this->warehouse));
        $this->assertSame(0, $this->stockOf($product, $this->store));

        $second = $service->execute($this->warehouse, $this->store, [['product_id' => $product->id, 'quantity' => 4]], null, $this->manager);
        app(SalesService::class)->create($this->store, [['product_id' => $product->id, 'quantity' => 3]], null, User::factory()->admin()->create());

        $this->expectException(InsufficientStockException::class);
        $service->cancel($second, $this->manager, 'Trop tard, déjà vendu');
    }

    // ------------------------------------------------------------ Achats

    public function test_une_commande_recue_en_deux_livraisons(): void
    {
        $supplier = Supplier::factory()->create(['lead_time_days' => 10]);
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $service = app(PurchaseService::class);

        $order = $service->createDraft([
            'supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $a->id, 'quantity' => 10, 'unit_cost' => 5000],
                ['product_id' => $b->id, 'quantity' => 4, 'unit_cost' => 20000],
            ],
        ], $this->manager);
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);

        $order = $service->confirm($order, $this->manager);
        $this->assertSame(PurchaseOrderStatus::Ordered, $order->status);
        $this->assertSame(today()->addDays(10)->toDateString(), $order->expected_at->toDateString());

        $items = $order->items()->get()->keyBy('product_id');
        $service->receive($order, [
            ['purchase_order_item_id' => $items[$a->id]->id, 'quantity' => 6],
            ['purchase_order_item_id' => $items[$b->id]->id, 'quantity' => 0],
        ], ['delivery_note_number' => 'BL-778'], $this->manager);

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);
        $this->assertSame(6, $this->stockOf($a, $this->warehouse));
        $this->assertSame(1, GoodsReceipt::first()->items()->count(), 'Les lignes à zéro ne sont pas enregistrées.');

        // Recevoir plus que le reste attendu est refusé.
        try {
            $service->receive($order, [['purchase_order_item_id' => $items[$a->id]->id, 'quantity' => 5]], [], $this->manager);
            $this->fail('Sur-réception acceptée.');
        } catch (BusinessException) {
            $this->assertSame(6, $this->stockOf($a, $this->warehouse));
        }

        $service->receive($order, [
            ['purchase_order_item_id' => $items[$a->id]->id, 'quantity' => 4],
            ['purchase_order_item_id' => $items[$b->id]->id, 'quantity' => 4],
        ], [], $this->manager);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame(10, $this->stockOf($a, $this->warehouse));
        $this->assertSame(4, $this->stockOf($b, $this->warehouse));
        $this->assertSame(2, GoodsReceipt::where('purchase_order_id', $order->id)->count());

        $this->expectException(InvalidStateTransitionException::class);
        $service->receive($order, [['purchase_order_item_id' => $items[$a->id]->id, 'quantity' => 1]], [], $this->manager);
    }

    public function test_une_commande_partielle_se_solde_et_ne_s_annule_plus(): void
    {
        $product = Product::factory()->create();
        $service = app(PurchaseService::class);
        $order = $service->confirm($service->createDraft([
            'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1000]],
        ], $this->manager), $this->manager);
        $service->receive($order, [['purchase_order_item_id' => $order->items()->first()->id, 'quantity' => 7]], [], $this->manager);

        try {
            $service->cancel($order, $this->manager, 'Plus besoin');
            $this->fail('Annulation d\'une commande déjà reçue en partie.');
        } catch (InvalidStateTransitionException) {
        }

        $service->close($order, $this->manager, 'Le fournisseur ne livrera pas le reliquat');
        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Closed, $order->status);
        $this->assertSame('Le fournisseur ne livrera pas le reliquat', $order->closure_reason);
        $this->assertSame(7, $this->stockOf($product, $this->warehouse));
    }

    public function test_un_brouillon_se_modifie_mais_pas_une_commande_passee(): void
    {
        $a = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $service = app(PurchaseService::class);
        $data = ['supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'lines' => [['product_id' => $a->id, 'quantity' => 2, 'unit_cost' => 100]]];

        $order = $service->createDraft($data, $this->manager);
        $data['lines'][0]['quantity'] = 9;
        $service->updateDraft($order, $data, $this->manager);
        $this->assertSame(9, $order->items()->first()->quantity_ordered);

        $service->confirm($order, $this->manager);
        $this->expectException(InvalidStateTransitionException::class);
        $service->updateDraft($order, $data, $this->manager);
    }

    public function test_une_reception_directe_entre_en_stock_sans_commande(): void
    {
        $product = Product::factory()->create();

        $receipt = app(PurchaseService::class)->receiveDirect(
            Supplier::factory()->create(), $this->warehouse,
            [['product_id' => $product->id, 'quantity' => 12, 'unit_cost' => 2500]], ['delivery_note_number' => 'BL-1'], $this->manager
        );

        $this->assertNull($receipt->purchase_order_id);
        $this->assertSame(12, $this->stockOf($product, $this->warehouse));
        $this->assertDatabaseHas('stock_movements', ['reference' => $receipt->reference, 'type' => 'purchase', 'quantity' => 12]);
    }
}
