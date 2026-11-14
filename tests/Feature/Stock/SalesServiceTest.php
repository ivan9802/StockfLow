<?php

namespace Tests\Feature\Stock;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\MissingReasonException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalesService $sales;

    private Warehouse $warehouse;

    private Warehouse $store;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = app(SalesService::class);
        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->agent = User::factory()->salesAgent($this->store)->create();
    }

    public function test_une_vente_decremente_le_stock_et_cree_les_mouvements(): void
    {
        $toner = Product::factory()->create(['selling_price' => 45000]);
        $paper = Product::factory()->create(['selling_price' => 3500]);
        $this->stock($toner, $this->store, 10);
        $this->stock($paper, $this->store, 50);

        $sale = $this->sales->create($this->store, [
            ['product_id' => $toner->id, 'quantity' => 2],
            ['product_id' => $paper->id, 'quantity' => 5],
        ], 'Cabinet Kouassi', $this->agent);

        $this->assertMatchesRegularExpression('/^BS-\d{4}-000001$/', $sale->reference);
        $this->assertSame(SaleStatus::Completed, $sale->status);
        $this->assertSame(2 * 45000 + 5 * 3500, $sale->total_amount);
        $this->assertSame(8, $this->stockOf($toner, $this->store));
        $this->assertSame(45, $this->stockOf($paper, $this->store));

        $movements = StockMovement::where('source_type', 'sale')->where('source_id', $sale->id)->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->every(fn ($m) => $m->type === MovementType::Sale && $m->reference === $sale->reference));
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'sale', 'auditable_id' => $sale->id, 'action' => AuditAction::Created->value]);
    }

    public function test_une_vente_dont_une_ligne_manque_de_stock_est_refusee_en_bloc(): void
    {
        $ok = Product::factory()->create();
        $short = Product::factory()->create(['name' => 'Cartouche HP 305 noire']);
        $this->stock($ok, $this->store, 10);
        $this->stock($short, $this->store, 3);
        $movements = StockMovement::count();

        try {
            $this->sales->create($this->store, [
                ['product_id' => $ok->id, 'quantity' => 2],
                ['product_id' => $short->id, 'quantity' => 5],
            ], null, $this->agent);
            $this->fail('La vente aurait dû être refusée.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Cartouche HP 305 noire : 3 disponibles, 5 demandés', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(10, $this->stockOf($ok, $this->store), 'La ligne disponible ne doit pas avoir été débitée.');
        $this->assertSame($movements, StockMovement::count());
    }

    public function test_un_produit_saisi_deux_fois_est_cumule_avant_le_controle(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 5);

        $this->expectException(InsufficientStockException::class);
        $this->sales->create($this->store, [
            ['product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $product->id, 'quantity' => 3],
        ], null, $this->agent);
    }

    public function test_le_stock_de_l_entrepot_ne_sert_pas_une_vente_en_boutique(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 100);

        $this->expectException(InsufficientStockException::class);
        $this->sales->create($this->store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->agent);
    }

    public function test_on_ne_vend_pas_depuis_l_entrepot_ni_un_produit_inactif(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 10);

        try {
            $this->sales->create($this->warehouse, [['product_id' => $product->id, 'quantity' => 1]], null, $this->agent);
            $this->fail('Vente depuis l\'entrepôt acceptée.');
        } catch (BusinessException) {
        }

        $inactive = Product::factory()->inactive()->create();
        $this->stock($inactive, $this->store, 10);
        $this->expectException(BusinessException::class);
        $this->sales->create($this->store, [['product_id' => $inactive->id, 'quantity' => 1]], null, $this->agent);
    }

    public function test_le_prix_est_celui_du_jour_et_survit_a_un_changement_de_tarif(): void
    {
        $product = Product::factory()->create(['selling_price' => 10000]);
        $this->stock($product, $this->store, 5);

        $sale = $this->sales->create($this->store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->agent);
        $product->update(['selling_price' => 15000]);

        $this->assertSame(10000, $sale->items()->first()->unit_price);
        $this->assertSame(10000, $sale->fresh()->total_amount);
    }

    public function test_l_annulation_restitue_le_stock_avec_un_motif(): void
    {
        $manager = User::factory()->stockManager()->create();
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 10);
        $sale = $this->sales->create($this->store, [['product_id' => $product->id, 'quantity' => 4]], null, $this->agent);

        try {
            $this->sales->cancel($sale, $manager, '  ');
            $this->fail('Annulation sans motif acceptée.');
        } catch (MissingReasonException) {
            $this->assertSame(6, $this->stockOf($product, $this->store));
        }

        $this->sales->cancel($sale, $manager, 'Client reparti sans payer');

        $sale->refresh();
        $this->assertSame(SaleStatus::Cancelled, $sale->status);
        $this->assertSame($manager->id, $sale->cancelled_by);
        $this->assertSame(10, $this->stockOf($product, $this->store));
        $this->assertDatabaseHas('stock_movements', [
            'source_type' => 'sale', 'source_id' => $sale->id, 'type' => 'sale_cancellation', 'quantity' => 4, 'reason' => 'Client reparti sans payer',
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->sales->cancel($sale, $manager, 'Deuxième annulation');
    }
}
