<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-16 : postes partagés au comptoir et à l'entrepôt. Le cookie de session est un
 * cookie de navigation, sans date d'expiration ni Max-Age : il disparaît à la
 * fermeture du navigateur. L'inactivité reste bornée par SESSION_LIFETIME.
 */
class SessionCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_configuration_ferme_la_session_avec_le_navigateur(): void
    {
        $this->assertTrue(config('session.expire_on_close'));
        $this->assertSame(120, config('session.lifetime'));
    }

    public function test_le_cookie_de_session_n_a_pas_d_expiration_apres_connexion(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-test']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $cookie = $response->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie, 'Cookie de session absent de la réponse.');
        $this->assertSame(0, $cookie->getExpiresTime());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertStringNotContainsStringIgnoringCase('max-age', (string) $cookie);
        $this->assertStringNotContainsStringIgnoringCase('expires', (string) $cookie);
    }
}
