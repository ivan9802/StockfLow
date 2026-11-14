<?php

namespace Tests\Feature\Catalog;

use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BarcodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $agent;

    private Warehouse $warehouse;

    private Warehouse $store;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->manager = User::factory()->stockManager()->create();
        $this->agent = User::factory()->salesAgent($this->store)->create();
        $this->category = Category::factory()->create(['code' => 'IMP', 'name' => 'Imprimantes']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Imprimante laser HP LaserJet M111w',
            'category_id' => $this->category->id,
            'purchase_price' => '85 000',
            'selling_price' => '110000',
            'alert_threshold' => 4,
            'is_active' => '1',
        ];
    }

    public function test_le_gestionnaire_cree_un_produit_avec_reference_et_code_barres_generes(): void
    {
        $response = $this->actingAs($this->manager)->post('/products', $this->payload());

        $product = Product::firstOrFail();
        $response->assertRedirect(route('products.show', $product));

        $this->assertSame('IMP-000001', $product->sku);
        $this->assertSame(85000, $product->purchase_price, 'Les espaces du montant saisi sont retirés.');
        $this->assertTrue(BarcodeGenerator::isValidEan13($product->barcode));
        $this->assertStringStartsWith('200', $product->barcode);
        $this->assertSame(2, $product->stockLevels()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Created->value, 'auditable_type' => 'product', 'auditable_id' => $product->id, 'user_id' => $this->manager->id,
        ]);
    }

    public function test_les_references_se_suivent_par_categorie(): void
    {
        $other = Category::factory()->create(['code' => 'ORD']);

        $this->actingAs($this->manager);
        $this->post('/products', $this->payload(['name' => 'A']));
        $this->post('/products', $this->payload(['name' => 'B']));
        $this->post('/products', $this->payload(['name' => 'C', 'category_id' => $other->id]));

        $this->assertEqualsCanonicalizing(['IMP-000001', 'IMP-000002', 'ORD-000001'], Product::pluck('sku')->all());
        $this->assertSame(3, Product::distinct()->count('barcode'));
    }

    public function test_un_code_barres_deja_utilise_est_refuse(): void
    {
        $existing = Product::factory()->create(['barcode' => '3760123456789']);

        $this->actingAs($this->manager)
            ->post('/products', $this->payload(['barcode' => $existing->barcode]))
            ->assertSessionHasErrors('barcode');

        $this->assertSame(1, Product::count());
    }

    public function test_les_champs_obligatoires_et_les_montants_sont_valides_cote_serveur(): void
    {
        $this->actingAs($this->manager)
            ->post('/products', ['name' => '', 'category_id' => 999999, 'purchase_price' => '-5', 'selling_price' => 'abc', 'alert_threshold' => -1])
            ->assertSessionHasErrors(['name', 'category_id', 'purchase_price', 'selling_price', 'alert_threshold']);

        $this->assertSame(0, Product::count());
    }

    public function test_le_vendeur_ne_peut_ni_creer_ni_modifier_ni_supprimer_un_produit(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->agent);
        $this->get('/products/create')->assertForbidden();
        $this->post('/products', $this->payload())->assertForbidden();
        $this->get("/products/{$product->id}/edit")->assertForbidden();
        $this->put("/products/{$product->id}", $this->payload())->assertForbidden();
        $this->delete("/products/{$product->id}")->assertForbidden();

        $this->assertSame(1, Product::count());
        $this->assertNotSame('Imprimante laser HP LaserJet M111w', $product->fresh()->name);
    }

    public function test_le_vendeur_consulte_le_catalogue_sans_voir_les_prix_d_achat(): void
    {
        $product = Product::factory()->create(['purchase_price' => 777700, 'selling_price' => 999900]);

        $this->actingAs($this->agent);
        $this->get('/products')->assertOk()->assertSee($product->name)->assertDontSee('777')->assertDontSee('Prix d\'achat');
        $this->get("/products/{$product->id}")->assertOk()->assertDontSee('777')->assertSee('999');

        $this->actingAs($this->manager)->get("/products/{$product->id}")->assertSee('777');
    }

    public function test_une_image_valide_est_enregistree_sous_un_nom_regenere(): void
    {
        Storage::fake('local');

        $this->actingAs($this->manager)->post('/products', $this->payload([
            'image' => UploadedFile::fake()->image('photo de mon produit.png', 300, 300),
        ]));

        $product = Product::firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->assertStringStartsWith('products/', $product->image_path);
        $this->assertStringNotContainsString('photo', $product->image_path);
        Storage::disk('local')->assertExists($product->image_path);

        $this->get("/products/{$product->id}/image")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        Storage::fake('local');
        $this->actingAs($this->manager);

        $fakes = [
            UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('facture.pdf', 10, 'application/pdf'),
            // Extension d'image, contenu qui n'en est pas un.
            UploadedFile::fake()->createWithContent('faux.png', '<?php echo "x";'),
            UploadedFile::fake()->image('trop-lourd.jpg')->size(3000),
        ];

        foreach ($fakes as $file) {
            $this->post('/products', $this->payload(['image' => $file]));
            $this->assertSame(0, Product::count(), 'Fichier accepté à tort : '.$file->getClientOriginalName());
        }

        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_l_image_n_est_pas_accessible_sans_connexion(): void
    {
        $product = Product::factory()->create(['image_path' => 'products/x.png']);

        $this->get("/products/{$product->id}/image")->assertRedirect('/login');
    }

    public function test_la_modification_garde_la_reference_et_trace_les_valeurs(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'sku' => 'IMP-000042', 'selling_price' => 100000]);
        $other = Category::factory()->create(['code' => 'ORD']);

        $this->actingAs($this->manager)->put("/products/{$product->id}", $this->payload([
            'category_id' => $other->id, 'selling_price' => 120000,
            'reorder_points' => [$this->store->id => 3, 999 => 5],
        ]))->assertRedirect();

        $product->refresh();
        $this->assertSame('IMP-000042', $product->sku);
        $this->assertSame(120000, $product->selling_price);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $this->store->id, 'reorder_point' => 3]);

        $log = \App\Models\AuditLog::where('auditable_type', 'product')->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(100000, $log->old_values['selling_price']);
        $this->assertSame(120000, $log->new_values['selling_price']);
    }

    public function test_un_produit_en_stock_ne_peut_pas_etre_supprime(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 3);

        $this->actingAs($this->manager)->from("/products/{$product->id}")
            ->delete("/products/{$product->id}")
            ->assertRedirect("/products/{$product->id}")
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($product);
    }

    public function test_un_produit_sans_stock_est_retire_du_catalogue_sans_perdre_son_historique(): void
    {
        $product = Product::factory()->create();
        $this->stock($product, $this->warehouse, 2);
        app(\App\Services\StockService::class)->decrease(
            $product->id, $this->warehouse->id, 2, \App\Enums\MovementType::InternalUse, $this->manager, 'Casse'
        );

        $this->actingAs($this->manager)->delete("/products/{$product->id}")->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
        $this->assertSame(2, \App\Models\StockMovement::where('product_id', $product->id)->count());
        $this->get('/products')->assertDontSee($product->name);
    }

    public function test_la_recherche_instantanee_trouve_par_nom_reference_et_code_barres(): void
    {
        $product = Product::factory()->create(['name' => 'Clavier sans fil Logitech K380', 'sku' => 'PER-000007', 'barcode' => '2000000000077']);
        Product::factory()->create(['name' => 'Souris optique']);
        Product::factory()->inactive()->create(['name' => 'Clavier retiré de la vente']);
        $this->stock($product, $this->store, 6);
        $this->stock($product, $this->warehouse, 10);

        $this->actingAs($this->agent);

        foreach (['clavier', 'PER-0000', '20000000000', 'K380'] as $term) {
            $data = $this->getJson('/internal/products/search?q='.urlencode($term))->assertOk()->json('data');
            $this->assertCount(1, $data, "Recherche « $term »");
            $this->assertSame($product->id, $data[0]['id']);
            $this->assertSame(16, $data[0]['stock']);
            $this->assertArrayNotHasKey('purchase_price', $data[0]);
        }

        $inStore = $this->getJson('/internal/products/search?q=clavier&warehouse_id='.$this->store->id)->json('data');
        $this->assertSame(6, $inStore[0]['stock']);
    }

    public function test_la_recherche_traite_les_caracteres_speciaux_comme_du_texte(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAs($this->agent);
        $this->getJson('/internal/products/search?q='.urlencode('%%'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/internal/products/search?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/internal/products/search?q=a')->assertStatus(422);
    }

    public function test_la_recherche_repond_en_moins_de_500_ms_sur_800_produits(): void
    {
        Product::factory()->count(800)->create(['category_id' => $this->category->id]);
        Product::factory()->create(['name' => 'Onduleur APC Back-UPS 650VA', 'category_id' => $this->category->id]);

        $this->actingAs($this->agent);
        $this->getJson('/internal/products/search?q=ondu'); // premier appel : chargement du framework

        $start = microtime(true);
        $this->getJson('/internal/products/search?q=onduleur')->assertOk()->assertJsonCount(1, 'data');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $elapsed, sprintf('Recherche en %.0f ms', $elapsed));
    }

    public function test_les_filtres_de_la_liste_portent_sur_l_etat_du_stock(): void
    {
        $ok = Product::factory()->create(['name' => 'Produit normal', 'alert_threshold' => 5]);
        $low = Product::factory()->create(['name' => 'Produit sous seuil', 'alert_threshold' => 5]);
        $out = Product::factory()->create(['name' => 'Produit en rupture', 'alert_threshold' => 5]);
        $this->stock($ok, $this->warehouse, 20);
        $this->stock($low, $this->warehouse, 3);
        $this->stock($low, $this->store, 2); // total 5 : égal au seuil, donc en alerte

        $this->actingAs($this->manager);
        $this->get('/products?stock=ok')->assertSee('Produit normal')->assertDontSee('Produit sous seuil')->assertDontSee('Produit en rupture');
        $this->get('/products?stock=low')->assertSee('Produit sous seuil')->assertDontSee('Produit normal')->assertDontSee('Produit en rupture');
        $this->get('/products?stock=out')->assertSee('Produit en rupture')->assertDontSee('Produit normal');
    }

    public function test_le_filtre_instantane_ne_renvoie_que_le_fragment_du_tableau(): void
    {
        Product::factory()->create(['name' => 'Routeur TP-Link Archer']);

        $html = $this->actingAs($this->manager)->get('/products?q=routeur', ['X-Partial' => '1'])->assertOk()->getContent();

        $this->assertStringContainsString('Routeur TP-Link Archer', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    public function test_une_categorie_qui_contient_des_produits_ne_peut_pas_etre_supprimee(): void
    {
        Product::factory()->create(['category_id' => $this->category->id]);
        $empty = Category::factory()->create();

        $this->actingAs($this->manager);
        $this->delete("/categories/{$this->category->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $this->category->id]);

        $this->delete("/categories/{$empty->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
    }

    public function test_le_code_d_une_categorie_est_valide_et_ne_change_plus(): void
    {
        $this->actingAs($this->manager);

        $this->post('/categories', ['name' => 'Réseau', 'code' => 'r1'])->assertSessionHasErrors('code');
        $this->post('/categories', ['name' => 'Réseau', 'code' => 'res'])->assertRedirect(route('categories.index'));
        $category = Category::where('name', 'Réseau')->firstOrFail();
        $this->assertSame('RES', $category->code);

        $this->put("/categories/{$category->id}", ['name' => 'Réseau et wifi', 'code' => 'ZZZ'])->assertRedirect();
        $this->assertSame('RES', $category->fresh()->code);
        $this->assertSame('Réseau et wifi', $category->fresh()->name);
    }

    public function test_les_categories_et_les_fournisseurs_sont_fermes_au_vendeur(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->agent);
        $this->get('/categories')->assertForbidden();
        $this->post('/categories', ['name' => 'X', 'code' => 'XXX'])->assertForbidden();
        $this->get('/suppliers')->assertForbidden();
        $this->post('/suppliers', ['name' => 'X', 'lead_time_days' => 3])->assertForbidden();
        $this->delete("/suppliers/{$supplier->id}")->assertForbidden();
    }

    public function test_le_gestionnaire_gere_les_fournisseurs_et_la_suppression_est_logique(): void
    {
        $this->actingAs($this->manager);

        $this->post('/suppliers', [
            'name' => 'CFAO Technologies', 'contact_name' => 'Awa Koné', 'email' => 'awa.kone@example.com',
            'phone' => '+225 07 08 09 10 11', 'lead_time_days' => 5, 'is_active' => '1',
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::firstOrFail();
        $this->assertSame(5, $supplier->lead_time_days);

        $this->post('/suppliers', ['name' => 'Y', 'lead_time_days' => 3, 'phone' => 'appelez-moi', 'email' => 'pas-un-email'])
            ->assertSessionHasErrors(['phone', 'email']);

        $this->delete("/suppliers/{$supplier->id}")->assertRedirect();
        $this->assertSoftDeleted($supplier);
    }

    public function test_un_texte_saisi_est_echappe_a_l_affichage(): void
    {
        $product = Product::factory()->create(['name' => '<script>alert("x")</script> Câble', 'description' => '<img src=x onerror=alert(1)>']);

        $html = $this->actingAs($this->manager)->get("/products/{$product->id}")->getContent();

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
