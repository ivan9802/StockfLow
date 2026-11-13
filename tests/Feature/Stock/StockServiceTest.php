<?php

namespace Tests\Feature\Stock;

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Events\StockLevelChanged;
use App\Exceptions\ImmutableRecordException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MissingReasonException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    private User $user;

    private Warehouse $warehouse;

    private Warehouse $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockService::class);
        $this->user = User::factory()->create();
        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->product = Product::factory()->create();
    }

    public function test_un_nouveau_produit_a_une_ligne_de_stock_a_zero_par_emplacement(): void
    {
        $this->assertSame(2, StockLevel::where('product_id', $this->product->id)->count());
        $this->assertSame(0, $this->stockOf($this->product, $this->warehouse));
        $this->assertSame(0, $this->stockOf($this->product, $this->store));
    }

    public function test_un_nouvel_emplacement_recoit_une_ligne_par_produit_existant(): void
    {
        $third = Warehouse::factory()->create();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $this->product->id, 'warehouse_id' => $third->id, 'quantity' => 0,
        ]);
    }

    public function test_une_entree_augmente_le_stock_et_cree_un_mouvement_complet(): void
    {
        $movement = $this->service->increase(
            $this->product->id, $this->warehouse->id, 12, MovementType::Purchase, $this->user, null, null, 'BR-2026-000001'
        );

        $this->assertSame(12, $this->stockOf($this->product, $this->warehouse));
        $this->assertSame(0, $this->stockOf($this->product, $this->store), 'L\'autre emplacement ne doit pas bouger.');

        $this->assertSame(MovementDirection::In, $movement->direction);
        $this->assertSame(12, $movement->quantity);
        $this->assertSame(0, $movement->stock_before);
        $this->assertSame(12, $movement->stock_after);
        $this->assertSame($this->user->id, $movement->user_id);
        $this->assertSame('BR-2026-000001', $movement->reference);
        $this->assertNotNull($movement->created_at);
    }

    public function test_une_sortie_diminue_le_stock_et_garde_le_stock_avant_et_apres(): void
    {
        $this->stock($this->product, $this->store, 10);

        $movement = $this->service->decrease(
            $this->product->id, $this->store->id, 4, MovementType::Sale, $this->user, null, null, 'BS-2026-000001'
        );

        $this->assertSame(6, $this->stockOf($this->product, $this->store));
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(6, $movement->stock_after);
        $this->assertSame(MovementDirection::Out, $movement->direction);
    }

    public function test_une_sortie_superieure_au_stock_est_refusee_sans_rien_ecrire(): void
    {
        $this->stock($this->product, $this->store, 3);
        $movementsBefore = StockMovement::count();

        try {
            $this->service->decrease($this->product->id, $this->store->id, 5, MovementType::Sale, $this->user, null, null, 'BS-X');
            $this->fail('La sortie aurait dû être refusée.');
        } catch (InsufficientStockException $e) {
            $shortage = $e->shortages()[0];
            $this->assertSame(5, $shortage['requested']);
            $this->assertSame(3, $shortage['available']);
            $this->assertStringContainsString($this->product->name, $e->getMessage());
        }

        $this->assertSame(3, $this->stockOf($this->product, $this->store));
        $this->assertSame($movementsBefore, StockMovement::count());
    }

    public function test_on_peut_sortir_exactement_tout_le_stock(): void
    {
        $this->stock($this->product, $this->store, 3);

        $this->service->decrease($this->product->id, $this->store->id, 3, MovementType::Sale, $this->user, null, null, 'BS-X');

        $this->assertSame(0, $this->stockOf($this->product, $this->store));
    }

    public function test_une_quantite_nulle_ou_negative_est_refusee(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->increase($this->product->id, $this->warehouse->id, 0, MovementType::Purchase, $this->user);
    }

    public function test_un_type_a_sens_unique_ne_peut_pas_etre_utilise_dans_l_autre_sens(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->increase($this->product->id, $this->warehouse->id, 1, MovementType::Sale, $this->user);
    }

    public function test_un_motif_est_obligatoire_pour_une_sortie_interne_et_un_ajustement(): void
    {
        $this->stock($this->product, $this->warehouse, 5);

        foreach ([MovementType::InternalUse, MovementType::ManualAdjustment] as $type) {
            try {
                $this->service->decrease($this->product->id, $this->warehouse->id, 1, $type, $this->user, '   ');
                $this->fail('Un motif vide aurait dû être refusé pour '.$type->value);
            } catch (MissingReasonException) {
                $this->assertSame(5, $this->stockOf($this->product, $this->warehouse));
            }
        }
    }

    public function test_un_mouvement_manuel_recoit_un_numero_de_bon(): void
    {
        $this->stock($this->product, $this->warehouse, 5);

        $first = $this->service->decrease($this->product->id, $this->warehouse->id, 1, MovementType::InternalUse, $this->user, 'Casse en rayon');
        $second = $this->service->increase($this->product->id, $this->warehouse->id, 2, MovementType::ManualAdjustment, $this->user, 'Carton retrouvé');

        $year = now()->format('Y');
        $this->assertSame("BM-$year-000001", $first->reference);
        $this->assertSame("BM-$year-000002", $second->reference);
        $this->assertSame('Casse en rayon', $first->reason);
    }

    public function test_un_mouvement_ne_peut_etre_ni_modifie_ni_supprime(): void
    {
        $movement = $this->service->increase($this->product->id, $this->warehouse->id, 5, MovementType::Purchase, $this->user, null, null, 'BR-X');

        try {
            $movement->update(['quantity' => 50]);
            $this->fail('La modification aurait dû être refusée.');
        } catch (ImmutableRecordException) {
            $this->assertSame(5, $movement->fresh()->quantity);
        }

        $this->expectException(ImmutableRecordException::class);
        $movement->delete();
    }

    public function test_la_base_refuse_un_stock_negatif_meme_si_le_service_est_contourne(): void
    {
        $this->expectException(QueryException::class);

        DB::table('stock_levels')
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update(['quantity' => DB::raw('quantity - 1')]);
    }

    public function test_le_controle_groupe_liste_tous_les_manques(): void
    {
        $other = Product::factory()->create();
        $enough = Product::factory()->create();
        $this->stock($this->product, $this->store, 2);
        $this->stock($other, $this->store, 1);
        $this->stock($enough, $this->store, 9);

        $lines = [
            ['product_id' => $this->product->id, 'quantity' => 5],
            ['product_id' => $other->id, 'quantity' => 3],
            ['product_id' => $enough->id, 'quantity' => 9],
        ];

        try {
            DB::transaction(function () use ($lines) {
                $levels = $this->service->lockLevels(array_map(fn ($l) => [$l['product_id'], $this->store->id], $lines));
                $this->service->assertAvailable($levels, $lines, $this->store->id);
            });
            $this->fail('Le contrôle aurait dû échouer.');
        } catch (InsufficientStockException $e) {
            $this->assertCount(2, $e->shortages());
            $this->assertEqualsCanonicalizing(
                [$this->product->id, $other->id],
                array_column($e->shortages(), 'product_id')
            );
        }
    }

    public function test_le_verrouillage_exige_une_transaction(): void
    {
        $this->expectException(LogicException::class);

        // RefreshDatabase enveloppe le test dans une transaction : on en sort pour ce cas.
        DB::rollBack();
        try {
            $this->service->lockLevels([[$this->product->id, $this->warehouse->id]]);
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_l_evenement_d_alerte_est_emis_apres_un_mouvement(): void
    {
        Event::fake([StockLevelChanged::class]);

        $this->service->increase($this->product->id, $this->warehouse->id, 5, MovementType::Purchase, $this->user, null, null, 'BR-X');

        Event::assertDispatched(StockLevelChanged::class, fn ($event) => $event->movement->quantity === 5);
    }

    public function test_la_somme_des_mouvements_egale_le_niveau_de_stock(): void
    {
        $this->service->increase($this->product->id, $this->warehouse->id, 20, MovementType::Purchase, $this->user, null, null, 'BR-1');
        $this->service->decrease($this->product->id, $this->warehouse->id, 7, MovementType::InternalUse, $this->user, 'Usage du bureau');
        $this->service->increase($this->product->id, $this->warehouse->id, 3, MovementType::ManualAdjustment, $this->user, 'Recomptage');
        $this->service->decrease($this->product->id, $this->warehouse->id, 16, MovementType::Transfer, $this->user, null, null, 'TR-1');

        $replayed = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->get()
            ->sum(fn (StockMovement $m) => $m->signedQuantity());

        $this->assertSame(0, $replayed);
        $this->assertSame(0, $this->stockOf($this->product, $this->warehouse));
    }
}
