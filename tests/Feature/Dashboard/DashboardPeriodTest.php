<?php

namespace Tests\Feature\Dashboard;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\TransferService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Indicateurs du tableau de bord sur une période : valeurs, comparaisons, graphiques,
 * droits et validation. Les données sont créées à des dates connues, puis les chiffres
 * attendus sont recalculés à la main dans chaque test.
 */
class DashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Warehouse $store;

    private User $manager;

    private User $agent;

    private User $otherAgent;

    private Product $toner;

    private Product $paper;

    protected function setUp(): void
    {
        parent::setUp();
        // Jeudi 19 novembre 2026, 15 h.
        Carbon::setTestNow('2026-11-19 15:00:00');
        CarbonImmutable::setTestNow('2026-11-19 15:00:00');

        [$this->warehouse, $this->store] = $this->createWarehouses();
        $this->manager = User::factory()->stockManager()->create();
        $this->agent = User::factory()->salesAgent($this->store)->create();
        $this->otherAgent = User::factory()->salesAgent($this->store)->create();
        $this->toner = Product::factory()->create(['name' => 'Toner HP 106A', 'selling_price' => 30000, 'alert_threshold' => 2]);
        $this->paper = Product::factory()->create(['name' => 'Ramette A4', 'selling_price' => 4000, 'alert_threshold' => 5]);

        $this->at('2026-09-01 08:00');
        $this->stock($this->toner, $this->store, 100, $this->manager);
        $this->stock($this->paper, $this->store, 200, $this->manager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow($moment);
    }

    /** Vente datée. $lines : [[produit, quantité], ...]. */
    private function sell(string $moment, array $lines, ?User $seller = null)
    {
        $this->at($moment);
        $sale = app(SalesService::class)->create(
            $this->store,
            array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
            null,
            $seller ?? $this->agent,
        );
        Carbon::setTestNow('2026-11-19 15:00:00');

        return $sale;
    }

    public function test_les_ventes_du_mois_sont_comparees_a_la_meme_tranche_du_mois_precedent(): void
    {
        // Mois précédent, du 1er au 19 octobre : 2 ventes, 68 000 F CFA.
        $this->sell('2026-10-05 10:00', [[$this->toner, 2]]);              // 60 000
        $this->sell('2026-10-19 18:00', [[$this->paper, 2]]);              //  8 000
        // Hors tranche de comparaison (20 octobre) : ignorée.
        $this->sell('2026-10-20 09:00', [[$this->toner, 5]]);
        // Mois en cours : 3 ventes, 102 000 F CFA, dont une annulée qui ne compte pas.
        $this->sell('2026-11-02 09:00', [[$this->toner, 3]]);              // 90 000
        $this->sell('2026-11-18 11:00', [[$this->paper, 3]]);              // 12 000
        $cancelled = $this->sell('2026-11-19 09:00', [[$this->toner, 1]]);
        app(SalesService::class)->cancel($cancelled, $this->manager, 'Erreur de saisie');

        $data = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=this_month')->assertOk()->json();

        $this->assertSame('2026-11-01', $data['period']['from']);
        $this->assertSame('2026-10-01', $data['previous']['from']);
        $this->assertSame('2026-10-19', $data['previous']['to']);

        $revenue = $data['kpis']['revenue'];
        $this->assertSame(102000, $revenue['value']);
        $this->assertSame(68000, $revenue['previous']);
        $this->assertEquals(50, $revenue['change'], '(102 000 - 68 000) / 68 000 = +50 %');
        $this->assertSame('up', $revenue['trend']);
        $this->assertSame('good', $revenue['tone']);

        $this->assertSame(2, $data['kpis']['sales_count']['value']);
        $this->assertSame(51000, $data['kpis']['average_basket']['value']);
        $this->assertSame(34000, $data['kpis']['average_basket']['previous']);
    }

    public function test_les_series_des_graphiques_couvrent_chaque_jour_et_somment_juste(): void
    {
        $this->sell('2026-11-14 10:00', [[$this->toner, 1]]);
        $this->sell('2026-11-14 16:00', [[$this->paper, 4]]);
        $this->sell('2026-11-17 12:00', [[$this->toner, 2]]);

        $data = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=last_7_days')->json();

        $sales = collect($data['charts']['sales']);
        $this->assertCount(7, $sales, 'Un point par jour, jours sans vente compris.');
        $this->assertSame(['13/11', '14/11', '15/11', '16/11', '17/11', '18/11', '19/11'], $sales->pluck('label')->all());
        $this->assertSame(46000, $sales->firstWhere('key', '2026-11-14')['revenue']);
        $this->assertSame(2, $sales->firstWhere('key', '2026-11-14')['count']);
        $this->assertSame(0, $sales->firstWhere('key', '2026-11-15')['revenue']);
        $this->assertSame($data['kpis']['revenue']['value'], $sales->sum('revenue'));

        $movements = collect($data['charts']['movements']);
        $this->assertSame(7, $movements->sum('out'), 'Sorties : 1 + 4 + 2 unités vendues.');
        $this->assertSame($data['kpis']['exits_units']['value'], $movements->sum('out'));
    }

    public function test_les_transferts_ne_comptent_ni_en_entree_ni_en_sortie(): void
    {
        $this->at('2026-11-10 09:00');
        app(TransferService::class)->execute($this->store, $this->warehouse, [['product_id' => $this->toner->id, 'quantity' => 10]], null, $this->manager);
        app(StockService::class)->decrease($this->paper->id, $this->store->id, 3, MovementType::InternalUse, $this->manager, 'Usage du bureau');
        Carbon::setTestNow('2026-11-19 15:00:00');

        $kpis = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=this_month')->json('kpis');

        $this->assertSame(1, $kpis['transfers_count']['value']);
        $this->assertSame(0, $kpis['entries_units']['value']);
        $this->assertSame(3, $kpis['exits_units']['value'], 'Seule la sortie interne compte.');
    }

    public function test_une_hausse_des_ajustements_est_presentee_comme_defavorable(): void
    {
        $stock = app(StockService::class);
        $this->at('2026-11-05 09:00');
        $stock->increase($this->toner->id, $this->store->id, 1, MovementType::ManualAdjustment, $this->manager, 'Carton retrouvé');
        $stock->decrease($this->toner->id, $this->store->id, 1, MovementType::ManualAdjustment, $this->manager, 'Erreur de saisie');
        $this->at('2026-10-05 09:00');
        $stock->increase($this->paper->id, $this->store->id, 1, MovementType::ManualAdjustment, $this->manager, 'Recomptage');
        Carbon::setTestNow('2026-11-19 15:00:00');

        $adjustments = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=this_month')->json('kpis.adjustments_count');

        $this->assertSame(2, $adjustments['value']);
        $this->assertSame(1, $adjustments['previous']);
        $this->assertEquals(100, $adjustments['change']);
        $this->assertSame('bad', $adjustments['tone']);
    }

    public function test_une_reference_nulle_ne_produit_pas_de_pourcentage(): void
    {
        $this->sell('2026-11-19 09:00', [[$this->toner, 1]]);

        $revenue = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=today')->json('kpis.revenue');

        $this->assertSame(0, $revenue['previous']);
        $this->assertNull($revenue['change'], 'Pas de division par zéro.');
        $this->assertSame('up', $revenue['trend']);
    }

    public function test_sans_comparaison_aucune_reference_n_est_calculee(): void
    {
        $data = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=this_month&compare=0')->json();

        $this->assertNull($data['previous']);
        $this->assertNull($data['kpis']['revenue']['previous']);
        $this->assertSame('neutral', $data['kpis']['revenue']['tone']);
    }

    public function test_les_produits_les_plus_vendus_sont_classes_par_unites(): void
    {
        $this->sell('2026-11-03 10:00', [[$this->toner, 2], [$this->paper, 5]]);
        $this->sell('2026-11-04 10:00', [[$this->paper, 1]]);

        $top = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=this_month')->json('top_products');

        $this->assertSame(['Ramette A4', 'Toner HP 106A'], array_column($top, 'name'));
        $this->assertSame(6, $top[0]['units']);
        $this->assertSame(24000, $top[0]['revenue']);
    }

    public function test_le_vendeur_ne_voit_que_ses_ventes_et_aucune_donnee_de_stock_sensible(): void
    {
        $this->sell('2026-11-10 10:00', [[$this->toner, 1]], $this->agent);
        $this->sell('2026-11-10 11:00', [[$this->toner, 3]], $this->otherAgent);

        $data = $this->actingAs($this->agent)->getJson('/internal/dashboard?period=this_month')->json();

        $this->assertSame(30000, $data['kpis']['revenue']['value']);
        $this->assertSame(1, $data['kpis']['sales_count']['value']);
        $this->assertSame([1], array_column($data['top_products'], 'units'));
        $this->assertArrayNotHasKey('stock_value', $data['snapshot']);
        $this->assertArrayNotHasKey('movements', $data['charts']);
        $this->assertArrayNotHasKey('warehouses', $data['charts']);
        $this->assertArrayNotHasKey('entries_units', $data['kpis']);
    }

    public function test_les_periodes_invalides_sont_refusees_par_le_serveur(): void
    {
        $this->actingAs($this->manager);

        $this->getJson('/internal/dashboard?period=custom&from=2026-11-10&to=2026-11-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/internal/dashboard?period=custom&from=2026-02-30&to=2026-03-02')
            ->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/internal/dashboard?period=custom&from=2020-01-01&to=2026-01-01')
            ->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson('/internal/dashboard?period=custom')
            ->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/internal/dashboard?period=depuis_toujours')
            ->assertStatus(422)->assertJsonValidationErrors('period');
    }

    public function test_une_periode_personnalisee_valide_est_appliquee(): void
    {
        $this->sell('2026-10-15 10:00', [[$this->toner, 1]]);
        $this->sell('2026-11-15 10:00', [[$this->toner, 1]]);

        $data = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=custom&from=2026-10-01&to=2026-10-31')->json();

        $this->assertSame(30000, $data['kpis']['revenue']['value']);
        $this->assertSame('2026-09-30', $data['previous']['to']);
        $this->assertCount(31, $data['charts']['sales']);
    }

    public function test_la_page_affiche_la_periode_et_ramene_une_periode_invalide_au_defaut(): void
    {
        $this->sell('2026-11-12 10:00', [[$this->toner, 1]]);
        $this->actingAs($this->manager);

        $this->get('/dashboard?period=this_month')->assertOk()
            ->assertSee('Ce mois-ci (du 01/11/2026 au 19/11/2026)')
            ->assertSee('dashboard-data', false)
            ->assertSee("30\u{00A0}000 F CFA");

        $this->get('/dashboard?period=custom&from=2026-11-10&to=2026-11-01')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');
    }

    public function test_l_etat_du_stock_reste_mesure_a_l_instant(): void
    {
        $snapshot = $this->actingAs($this->manager)->getJson('/internal/dashboard?period=last_year')->json('snapshot');

        $this->assertSame(300, $snapshot['total_units'], 'Indépendant de la période choisie.');
        $this->assertSame(100 * $this->toner->purchase_price + 200 * $this->paper->purchase_price, $snapshot['stock_value']);
    }
}
