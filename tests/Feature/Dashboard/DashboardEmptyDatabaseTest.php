<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Première installation : ni produit, ni emplacement, ni vente. */
class DashboardEmptyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tableau_de_bord_s_affiche_sur_une_base_vide(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/dashboard')->assertOk()->assertSee('Aucun produit sous son seuil');

        $data = $this->getJson('/internal/dashboard')->assertOk()->json();
        $this->assertSame([], $data['charts']['warehouses']);
        $this->assertSame([], $data['top_products']);
        $this->assertSame(0, $data['snapshot']['total_units']);
        $this->assertSame(0, $data['kpis']['revenue']['value']);
    }

    public function test_le_vendeur_ouvre_son_tableau_de_bord_sans_aucune_vente(): void
    {
        $this->actingAs(User::factory()->salesAgent()->create());

        $this->get('/dashboard')->assertOk()->assertSee('Mon chiffre d')->assertSee('Aucune vente pour l');
    }
}
