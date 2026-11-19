<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-21 : les en-têtes de sécurité partent sur toutes les réponses, y compris les
 * erreurs rendues hors du groupe web et la route de santé /up, sans casser la
 * politique de contenu à nonce des pages Blade.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_route_de_sante_porte_les_en_tetes(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_une_page_introuvable_porte_les_en_tetes(): void
    {
        $this->get('/adresse-qui-n-existe-pas')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_une_erreur_json_porte_les_en_tetes(): void
    {
        $this->getJson('/internal/adresse-qui-n-existe-pas')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_le_nonce_de_la_politique_de_contenu_correspond_a_la_page(): void
    {
        foreach (['/login', '/adresse-qui-n-existe-pas'] as $uri) {
            $response = $this->get($uri);

            $this->assertMatchesRegularExpression(
                "/script-src 'self' 'nonce-([^']+)'/",
                $response->headers->get('Content-Security-Policy'),
                $uri
            );
            preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);

            $this->assertStringContainsString('nonce="'.$m[1].'"', $response->getContent(), $uri);
        }
    }
}
