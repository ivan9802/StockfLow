<?php

namespace Tests\Feature\Stock;

use App\Enums\InventoryStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryAlreadyOpenException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Category;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Warehouse $store;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        [, $this->store] = $this->createWarehouses();
        $this->manager = User::factory()->stockManager()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function countLine(InventorySession $session, Product $product, int $counted, ?string $why = null): void
    {
        $item = $session->items()->where('product_id', $product->id)->firstOrFail();
        $this->inventory->recordCounts($session, [$item->id => ['counted_quantity' => $counted, 'justification' => $why]], $this->manager);
    }

    public function test_cycle_complet_avec_ecarts_justifies_et_regularisation(): void
    {
        $plus = Product::factory()->create();
        $minus = Product::factory()->create();
        $same = Product::factory()->create();
        $this->stock($plus, $this->store, 10);
        $this->stock($minus, $this->store, 12);
        $this->stock($same, $this->store, 5);

        $session = $this->inventory->open($this->store, null, $this->manager);
        $this->assertSame(3, $session->items()->count());

        $this->countLine($session, $plus, 11, 'Carton non saisi à la réception');
        $this->countLine($session, $minus, 10, 'Deux unités cassées en rayon');
        $this->countLine($session, $same, 5);

        $item = $session->items()->where('product_id', $minus->id)->first();
        $this->assertSame(12, $item->theoretical_quantity);
        $this->assertSame(-2, $item->variance);

        $this->inventory->submit($session, $this->manager);
        $this->inventory->validate($session, $this->admin);

        $this->assertSame(InventoryStatus::Validated, $session->fresh()->status);
        $this->assertSame(11, $this->stockOf($plus, $this->store));
        $this->assertSame(10, $this->stockOf($minus, $this->store));
        $this->assertSame(5, $this->stockOf($same, $this->store));
        $this->assertSame(2, StockMovement::where('type', 'inventory_adjustment')->where('reference', $session->reference)->count());
        $this->assertStringContainsString('Deux unités cassées', StockMovement::where('product_id', $minus->id)->where('type', 'inventory_adjustment')->value('reason'));
    }

    public function test_une_vente_faite_avant_le_comptage_n_est_pas_retranchee_deux_fois(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 10);
        $session = $this->inventory->open($this->store, null, $this->manager);

        // Trois unités vendues pendant que le compteur est dans un autre rayon.
        app(SalesService::class)->create($this->store, [['product_id' => $product->id, 'quantity' => 3]], null, $this->admin);

        // Le compteur trouve 7 unités : c'est exact.
        $this->countLine($session, $product, 7);
        $this->inventory->submit($session, $this->manager);
        $this->inventory->validate($session, $this->admin);

        $this->assertSame(7, $this->stockOf($product, $this->store), 'Le relevé à l\'ouverture aurait donné 4.');
    }

    public function test_une_vente_faite_apres_le_comptage_est_preservee(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 12);
        $session = $this->inventory->open($this->store, null, $this->manager);

        $this->countLine($session, $product, 10, 'Deux unités manquantes');
        app(SalesService::class)->create($this->store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->admin);
        $this->inventory->submit($session, $this->manager);
        $this->inventory->validate($session, $this->admin);

        // 12 - 1 vendu = 11, puis écart de -2 : 9.
        $this->assertSame(9, $this->stockOf($product, $this->store));
    }

    public function test_un_ecart_non_justifie_bloque_la_soumission(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 8);
        $session = $this->inventory->open($this->store, null, $this->manager);

        try {
            $this->inventory->submit($session, $this->manager);
            $this->fail('Soumission avec une ligne non comptée.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('reste 1 ligne', $e->getMessage());
        }

        $this->countLine($session, $product, 6);
        $this->expectException(BusinessException::class);
        $this->inventory->submit($session, $this->manager);
    }

    public function test_une_seule_session_ouverte_par_emplacement(): void
    {
        Product::factory()->create();
        $this->inventory->open($this->store, null, $this->manager);

        $this->expectException(InventoryAlreadyOpenException::class);
        $this->inventory->open($this->store, null, $this->manager);
    }

    public function test_un_inventaire_par_categorie_ne_compte_que_ses_produits(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);
        Product::factory()->count(3)->create();

        $session = $this->inventory->open($this->store, $category, $this->manager);

        $this->assertSame(2, $session->items()->count());
    }

    public function test_renvoi_en_comptage_puis_validation_refusee_si_le_stock_deviendrait_negatif(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 5);
        $session = $this->inventory->open($this->store, null, $this->manager);
        $this->countLine($session, $product, 1, 'Quatre unités introuvables');
        $this->inventory->submit($session, $this->manager);

        $this->inventory->sendBack($session, $this->admin, 'Recomptez la réserve du fond');
        $session->refresh();
        $this->assertSame(InventoryStatus::InProgress, $session->status);
        $this->assertSame('Recomptez la réserve du fond', $session->review_comment);

        $this->countLine($session, $product, 1, 'Recompté, toujours une seule');
        $this->inventory->submit($session, $this->manager);

        // Entre-temps, 3 unités sont vendues : le stock courant (2) ne couvre plus l'écart de -4.
        app(SalesService::class)->create($this->store, [['product_id' => $product->id, 'quantity' => 3]], null, $this->admin);

        try {
            $this->inventory->validate($session, $this->admin);
            $this->fail('Validation qui rendrait le stock négatif acceptée.');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(InventoryStatus::PendingValidation, $session->fresh()->status);
        $this->assertSame(2, $this->stockOf($product, $this->store));
    }

    public function test_une_session_validee_ne_s_annule_plus(): void
    {
        $product = Product::factory()->create();
        $session = $this->inventory->open($this->store, null, $this->manager);
        $this->countLine($session, $product, 0);
        $this->inventory->submit($session, $this->manager);
        $this->inventory->validate($session, $this->admin);

        $this->expectException(InvalidStateTransitionException::class);
        $this->inventory->cancel($session, $this->admin, 'Trop tard pour annuler');
    }
}
