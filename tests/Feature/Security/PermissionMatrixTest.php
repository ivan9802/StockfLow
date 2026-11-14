<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matrice des droits vérifiée par requête HTTP directe : masquer un bouton ne
 * protège rien, c'est la route qui doit refuser. Une cellule « non » doit renvoyer 403.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private array $users = [];

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$warehouse, $store] = $this->createWarehouses();
        $this->users = [
            'admin' => User::factory()->admin()->create(),
            'manager' => User::factory()->stockManager()->create(),
            'agent' => User::factory()->salesAgent($store)->create(),
        ];

        $product = Product::factory()->create();
        $this->stock($product, $warehouse, 20);
        $this->stock($product, $store, 20);
        $supplier = Supplier::factory()->create();

        $sale = app(SalesService::class)->create($store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->users['admin']);
        $transfer = app(TransferService::class)->execute($warehouse, $store, [['product_id' => $product->id, 'quantity' => 1]], null, $this->users['manager']);
        $order = app(PurchaseService::class)->createDraft(['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 100]]], $this->users['manager']);
        $inventory = app(InventoryService::class)->open($store, null, $this->users['manager']);

        $this->ids = [
            '{product}' => $product->id, '{category}' => Category::first()->id, '{supplier}' => $supplier->id,
            '{sale}' => $sale->id, '{transfer}' => $transfer->id, '{order}' => $order->id,
            '{inventory}' => $inventory->id, '{user}' => $this->users['manager']->id, '{warehouse}' => $warehouse->id,
        ];
    }

    /**
     * [méthode, adresse, admin, gestionnaire, vendeur] : true = autorisé, false = 403.
     */
    public static function matrix(): array
    {
        return [
            'catalogue consultation' => ['GET', '/products', true, true, true],
            'fiche produit' => ['GET', '/products/{product}', true, true, true],
            'recherche JSON' => ['GET', '/internal/products/search?q=ab', true, true, true],
            'création produit' => ['GET', '/products/create', true, true, false],
            'suppression produit' => ['DELETE', '/products/{product}', true, true, false],
            'catégories' => ['GET', '/categories', true, true, false],
            'fournisseurs' => ['GET', '/suppliers', true, true, false],
            'état des stocks' => ['GET', '/stock', true, true, true],
            'historique mouvements' => ['GET', '/movements', true, true, false],
            'saisie mouvement' => ['POST', '/movements', true, true, false],
            'transferts' => ['GET', '/transfers', true, true, false],
            'nouveau transfert' => ['POST', '/transfers', true, true, false],
            'annulation transfert' => ['POST', '/transfers/{transfer}/cancel', true, true, false],
            'commandes' => ['GET', '/purchase-orders', true, true, false],
            'passer commande' => ['POST', '/purchase-orders/{order}/confirm', true, true, false],
            'réceptions' => ['GET', '/goods-receipts', true, true, false],
            'inventaires' => ['GET', '/inventories', true, true, false],
            'comptage' => ['PUT', '/inventories/{inventory}/counts', true, true, false],
            'validation inventaire' => ['POST', '/inventories/{inventory}/validate', true, false, false],
            'renvoi inventaire' => ['POST', '/inventories/{inventory}/send-back', true, false, false],
            'point de vente' => ['GET', '/sales/create', true, false, true],
            'enregistrer vente' => ['POST', '/sales', true, false, true],
            'historique ventes' => ['GET', '/sales', true, true, true],
            'annulation vente' => ['POST', '/sales/{sale}/cancel', true, true, false],
            'alertes' => ['GET', '/alerts', true, true, false],
            'rapports' => ['GET', '/reports', true, true, false],
            'export stock' => ['GET', '/reports/stock.csv', true, true, false],
            'utilisateurs' => ['GET', '/users', true, false, false],
            'création compte' => ['POST', '/users', true, false, false],
            'réinitialisation mot de passe' => ['POST', '/users/{user}/reset-password', true, false, false],
            'emplacements' => ['GET', '/warehouses', true, false, false],
            'journal audit' => ['GET', '/audit-logs', true, false, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_chaque_route_respecte_la_matrice_des_droits(string $method, string $uri, bool $admin, bool $manager, bool $agent): void
    {
        $uri = strtr($uri, $this->ids);

        foreach (['admin' => $admin, 'manager' => $manager, 'agent' => $agent] as $role => $allowed) {
            $status = $this->actingAs($this->users[$role])->json($method, $uri)->getStatusCode();

            if ($allowed) {
                $this->assertNotSame(403, $status, "$role devrait pouvoir accéder à $method $uri");
            } else {
                $this->assertSame(403, $status, "$role ne devrait pas pouvoir accéder à $method $uri (statut $status)");
            }
        }
    }

    public function test_un_vendeur_ne_voit_pas_la_vente_d_un_autre(): void
    {
        $store = Warehouse::where('code', 'PDV')->first();
        $other = User::factory()->salesAgent($store)->create();
        $sale = Sale::first();

        $this->actingAs($other)->get("/sales/{$sale->id}")->assertForbidden();
        $this->actingAs($other)->get("/sales/{$sale->id}/print")->assertForbidden();
        $this->actingAs($other)->get('/sales')->assertOk()->assertDontSee($sale->reference);
        $this->actingAs($this->users['manager'])->get('/sales')->assertSee($sale->reference);
    }

    public function test_un_vendeur_ne_peut_pas_vendre_depuis_un_autre_emplacement(): void
    {
        $warehouse = Warehouse::where('code', 'ENT')->first();
        $otherStore = Warehouse::factory()->pointOfSale()->create();
        $product = Product::first();
        $this->stock($product, $otherStore, 5);

        foreach ([$warehouse->id, $otherStore->id] as $target) {
            $this->actingAs($this->users['agent'])->postJson('/sales', [
                'warehouse_id' => $target, 'form_token' => str_repeat('a', 39).$target % 10,
                'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertForbidden();
        }

        $this->assertSame(5, $this->stockOf($product, $otherStore));
    }

    public function test_un_compte_desactive_perd_l_acces_aux_points_json(): void
    {
        $this->users['manager']->update(['is_active' => false]);

        $this->actingAs($this->users['manager'])->getJson('/internal/products/search?q=ab')->assertUnauthorized();
    }
}
