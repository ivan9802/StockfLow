<?php

namespace Tests\Feature\Modules;

use App\Enums\InventoryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Parcours complets par HTTP : formulaires, redirections, messages, effets sur le stock. */
class OperationsHttpTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Warehouse $store;

    private User $admin;

    private User $manager;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->stockManager()->create();
        $this->agent = User::factory()->salesAgent($this->store)->create();
    }

    public function test_le_vendeur_enregistre_une_vente_au_point_de_vente(): void
    {
        $product = Product::factory()->create(['selling_price' => 25000]);
        $this->stock($product, $this->store, 6);

        $this->actingAs($this->agent)->get('/sales/create')->assertOk()->assertSee('Point de vente')->assertSee($this->store->name);

        $response = $this->postJson('/sales', [
            'form_token' => Str::random(40),
            'customer_name' => 'Mme Traoré',
            'lines' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonStructure(['reference', 'url', 'print_url']);
        $this->assertSame(4, $this->stockOf($product, $this->store));
        $sale = Sale::firstOrFail();
        $this->assertSame(50000, $sale->total_amount);

        $this->get("/sales/{$sale->id}/print")->assertOk()->assertSee('Bon de sortie')->assertSee($sale->reference)->assertSee('Mme Traoré');
    }

    public function test_une_vente_en_stock_insuffisant_renvoie_le_detail_des_manques(): void
    {
        $product = Product::factory()->create(['name' => 'Disque SSD 512 Go']);
        $this->stock($product, $this->store, 1);

        $this->actingAs($this->agent)->postJson('/sales', [
            'form_token' => Str::random(40),
            'lines' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertStatus(422)
            ->assertJsonPath('shortages.0.available', 1)
            ->assertJsonPath('shortages.0.requested', 3)
            ->assertJsonFragment(['product_name' => 'Disque SSD 512 Go']);

        $this->assertSame(0, Sale::count());
    }

    public function test_un_double_envoi_du_meme_panier_ne_cree_qu_une_vente(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 10);
        $payload = ['form_token' => Str::random(40), 'lines' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->actingAs($this->agent)->postJson('/sales', $payload)->assertCreated();
        $this->postJson('/sales', $payload)->assertStatus(409);

        $this->assertSame(1, Sale::count());
        $this->assertSame(9, $this->stockOf($product, $this->store));
    }

    public function test_le_gestionnaire_annule_une_vente_avec_un_motif(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 5);
        $this->actingAs($this->agent)->postJson('/sales', ['form_token' => Str::random(40), 'lines' => [['product_id' => $product->id, 'quantity' => 2]]]);
        $sale = Sale::firstOrFail();

        $this->actingAs($this->manager)->post("/sales/{$sale->id}/cancel", ['reason' => 'abc'])->assertSessionHasErrors('reason');
        $this->post("/sales/{$sale->id}/cancel", ['reason' => 'Article retourné défectueux'])->assertRedirect("/sales/{$sale->id}");

        $this->assertSame(5, $this->stockOf($product, $this->store));
        $this->get("/sales/{$sale->id}")->assertSee('Vente annulée')->assertSee('Article retourné défectueux');
    }

    public function test_sortie_interne_et_ajustement_par_le_formulaire(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 10);
        $this->actingAs($this->manager);

        $this->post('/movements', ['kind' => 'internal_use', 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 2, 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->post('/movements', ['kind' => 'internal_use', 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 2, 'reason' => 'Imprimante du service comptable'])
            ->assertRedirect()->assertSessionHas('success');
        $this->post('/movements', ['kind' => 'adjustment_in', 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 1, 'reason' => 'Carton retrouvé en réserve'])
            ->assertSessionHas('success');
        $this->post('/movements', ['kind' => 'adjustment_out', 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 50, 'reason' => 'Correction de saisie'])
            ->assertSessionHas('error');

        $this->assertSame(9, $this->stockOf($product, $this->warehouse));
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'stock_movement', 'user_id' => $this->manager->id]);
        $this->get("/movements?product_id={$product->id}")->assertOk()->assertSee('Imprimante du service comptable')->assertSee('Fiche de stock');
    }

    public function test_transfert_par_le_formulaire_puis_bon_imprimable(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 30);

        $this->actingAs($this->manager)->post('/transfers', [
            'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertSessionHasErrors('to_warehouse_id');

        $this->post('/transfers', [
            'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->store->id,
            'lines' => [['product_id' => $product->id, 'quantity' => '12'], ['product_id' => '', 'quantity' => '']],
        ])->assertRedirect();

        $transfer = StockTransfer::firstOrFail();
        $this->assertSame(18, $this->stockOf($product, $this->warehouse));
        $this->assertSame(12, $this->stockOf($product, $this->store));
        $this->get("/transfers/{$transfer->id}/print")->assertOk()->assertSee('Bon de transfert')->assertSee($transfer->reference);
    }

    public function test_commande_passee_puis_receptionnee_en_deux_fois_par_les_ecrans(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->manager);

        $this->post('/purchase-orders', [
            'supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => '12 000']],
        ])->assertRedirect();
        $order = PurchaseOrder::firstOrFail();
        $this->assertSame(12000, $order->items()->first()->unit_cost);

        $this->get("/purchase-orders/{$order->id}/receive")->assertRedirect("/purchase-orders/{$order->id}");
        $this->post("/purchase-orders/{$order->id}/confirm")->assertSessionHas('success');

        $item = $order->items()->first();
        $this->get("/purchase-orders/{$order->id}/receive")->assertOk()->assertSee('Reçu aujourd');
        $this->post("/purchase-orders/{$order->id}/receive", ['quantities' => [$item->id => 4], 'delivery_note_number' => 'BL-2201'])->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);

        $this->post("/purchase-orders/{$order->id}/receive", ['quantities' => [$item->id => 7]])->assertSessionHas('error');
        $this->post("/purchase-orders/{$order->id}/receive", ['quantities' => [$item->id => 6]])->assertRedirect();

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame(10, $this->stockOf($product, $this->warehouse));
        $receipt = GoodsReceipt::firstOrFail();
        $this->get("/goods-receipts/{$receipt->id}/print")->assertOk()->assertSee('BL-2201');
    }

    public function test_inventaire_par_les_ecrans_avec_comptage_json_et_validation(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->store, 8);
        $this->actingAs($this->manager);

        $this->post('/inventories', ['warehouse_id' => $this->store->id])->assertRedirect();
        $session = InventorySession::firstOrFail();
        $item = $session->items()->firstOrFail();

        $this->get("/inventories/{$session->id}")->assertOk()->assertSee($product->name);
        $this->get("/inventories/{$session->id}/print")->assertOk()->assertDontSee('Théorique');

        $this->putJson("/inventories/{$session->id}/counts", ['counts' => [$item->id => ['counted_quantity' => 6, 'justification' => '']]])
            ->assertOk()->assertJsonPath('items.0.variance', -2)->assertJsonPath('counted', 1);

        $this->post("/inventories/{$session->id}/submit")->assertSessionHas('error');
        $this->putJson("/inventories/{$session->id}/counts", ['counts' => [$item->id => ['counted_quantity' => 6, 'justification' => 'Deux unités volées']]]);
        $this->post("/inventories/{$session->id}/submit")->assertSessionHas('success');

        $this->post("/inventories/{$session->id}/validate")->assertForbidden();
        $this->actingAs($this->admin)->post("/inventories/{$session->id}/validate")->assertSessionHas('success');

        $this->assertSame(InventoryStatus::Validated, $session->fresh()->status);
        $this->assertSame(6, $this->stockOf($product, $this->store));
    }

    public function test_une_alerte_est_creee_au_franchissement_du_seuil_et_une_seule_fois(): void
    {
        $product = Product::factory()->create(['alert_threshold' => 5, 'name' => 'Ramette A4 80 g']);
        $this->stock($product, $this->store, 7);
        $this->actingAs($this->agent);

        $this->postJson('/sales', ['form_token' => Str::random(40), 'lines' => [['product_id' => $product->id, 'quantity' => 2]]])->assertCreated();
        $this->assertSame(1, $this->manager->notifications()->count(), 'Passage de 7 à 5 : alerte attendue.');
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(0, $this->agent->notifications()->count(), 'Le vendeur ne reçoit pas les alertes.');

        $this->postJson('/sales', ['form_token' => Str::random(40), 'lines' => [['product_id' => $product->id, 'quantity' => 1]]])->assertCreated();
        $this->assertSame(1, $this->manager->notifications()->count(), 'Pas de doublon tant que l\'alerte n\'est pas lue.');

        $notification = $this->manager->notifications()->first();
        $this->assertSame(LowStockNotification::class, $notification->type);
        $this->assertStringContainsString('Ramette A4 80 g', $notification->data['message']);

        $this->actingAs($this->manager)->get('/alerts')->assertOk()->assertSee('Ramette A4 80 g');
        $this->get('/dashboard')->assertOk()->assertSee('Ramette A4 80 g');
    }

    public function test_les_kpi_du_tableau_de_bord_egalent_les_agregats_recalcules(): void
    {
        $a = Product::factory()->create(['purchase_price' => 1000, 'alert_threshold' => 2]);
        $b = Product::factory()->create(['purchase_price' => 2500, 'alert_threshold' => 5]);
        Product::factory()->create(['alert_threshold' => 1]);
        $this->stock($a, $this->warehouse, 10);
        $this->stock($b, $this->store, 4);

        $data = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=today')->assertOk()->json();

        $this->assertSame(10 * 1000 + 4 * 2500, $data['snapshot']['stock_value']);
        $this->assertSame(14, $data['snapshot']['total_units']);
        $this->assertSame(3, $data['snapshot']['active_products']);
        $this->assertSame(1, $data['snapshot']['low_count']);
        $this->assertSame(1, $data['snapshot']['out_count']);
        $this->assertSame(14, $data['kpis']['entries_units']['value'], 'Deux réceptions du jour : 10 + 4 unités.');

        $agentData = $this->actingAs($this->agent)->getJson('/internal/dashboard')->json();
        $this->assertArrayNotHasKey('stock_value', $agentData['snapshot'], 'Le vendeur ne voit pas la valeur du stock.');
        $this->assertArrayNotHasKey('entries_units', $agentData['kpis'], 'Le vendeur ne voit pas les mouvements.');
    }

    public function test_les_exports_csv_et_pdf(): void
    {
        $product = Product::factory()->create(['name' => '=HYPERLINK("http://x") Câble']);
        $this->stock($product, $this->warehouse, 3);
        $this->actingAs($this->manager);

        $csv = $this->get('/reports/stock.csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Une formule doit être neutralisée.');
        $this->assertCount(2, array_filter(explode("\n", trim($csv))));

        $movements = $this->get('/reports/movements.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('BR-TEST-000001', $movements);

        $pdf = $this->get('/reports/stock.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'user_id' => $this->manager->id]);
    }

    public function test_l_administrateur_cree_un_vendeur_et_reinitialise_son_mot_de_passe(): void
    {
        $this->actingAs($this->admin);

        $this->post('/users', ['name' => 'Koffi Yao', 'email' => 'koffi@example.com', 'role' => 'sales_agent', 'is_active' => '1'])
            ->assertSessionHasErrors('warehouse_id');

        $this->post('/users', ['name' => 'Koffi Yao', 'email' => 'koffi@example.com', 'role' => 'sales_agent', 'warehouse_id' => $this->store->id, 'is_active' => '1'])
            ->assertRedirect('/users');
        $user = User::where('email', 'koffi@example.com')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertStringContainsString('Mot de passe provisoire', session('success'));

        $this->post("/users/{$user->id}/reset-password")->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'user', 'auditable_id' => $user->id, 'action' => 'updated']);

        $this->put("/users/{$this->admin->id}", ['name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'stock_manager', 'is_active' => '1'])
            ->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_le_journal_d_audit_est_consultable_et_filtrable(): void
    {
        Product::factory()->create(['name' => 'Webcam Logitech C270']);

        $this->actingAs($this->admin)->get('/audit-logs')->assertOk()->assertSee('Création');
        $this->get('/audit-logs?subject=product')->assertOk()->assertSee('Produit');
        $this->get('/audit-logs?action=deleted')->assertOk()->assertSee('Aucune entrée');
    }

    public function test_tous_les_ecrans_s_affichent_sans_erreur(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 5);

        foreach (['/dashboard', '/products', "/products/{$product->id}", '/products/create', '/categories', '/categories/create',
            '/suppliers', '/suppliers/create', '/stock', '/movements', '/movements/create', '/transfers', '/transfers/create',
            '/purchase-orders', '/purchase-orders/create', '/goods-receipts', '/goods-receipts/create', '/inventories',
            '/inventories/create', '/sales', '/sales/create', '/alerts', '/reports', '/users', '/users/create',
            '/warehouses', "/warehouses/{$this->store->id}/edit", '/audit-logs', '/password', '/legal'] as $uri) {
            $this->actingAs($this->admin)->get($uri)->assertOk();
        }

        $this->actingAs($this->agent)->get('/dashboard')->assertOk()->assertSee('Mon chiffre d')->assertDontSee('Valeur du stock');
    }
}
