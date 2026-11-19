<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Proxies de confiance lus dans config('stockflow.trusted_proxies') à chaque requête
 * (App\Http\Middleware\TrustProxies). Derrière le proxy TLS, le schéma HTTPS n'est
 * reconnu, et HSTS envoyé, que si l'adresse du proxy est déclarée de confiance.
 * L'adresse du client simulé par les tests est 127.0.0.1.
 */
class TrustedProxiesTest extends TestCase
{
    private const FORWARDED = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '203.0.113.7',
    ];

    public function test_tous_les_proxies_de_confiance_rendent_la_requete_https(): void
    {
        config(['stockflow.trusted_proxies' => '*']);

        $this->get('/up', self::FORWARDED)
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_une_liste_d_adresses_contenant_le_proxy_est_appliquee(): void
    {
        config(['stockflow.trusted_proxies' => '10.0.0.1, 127.0.0.1']);

        $this->get('/up', self::FORWARDED)->assertHeader('Strict-Transport-Security');
    }

    public function test_l_adresse_du_client_vient_de_x_forwarded_for(): void
    {
        config(['stockflow.trusted_proxies' => '*']);

        $this->get('/up', self::FORWARDED);

        $this->assertSame('203.0.113.7', request()->ip());
        $this->assertTrue(request()->isSecure());
    }

    public function test_sans_proxy_de_confiance_les_en_tetes_transmis_sont_ignores(): void
    {
        config(['stockflow.trusted_proxies' => null]);

        $this->get('/up', self::FORWARDED)->assertOk()->assertHeaderMissing('Strict-Transport-Security');

        $this->assertSame('127.0.0.1', request()->ip());
    }

    public function test_un_proxy_absent_de_la_liste_n_est_pas_cru(): void
    {
        config(['stockflow.trusted_proxies' => '10.0.0.1']);

        $this->get('/up', self::FORWARDED)->assertHeaderMissing('Strict-Transport-Security');
    }
}
