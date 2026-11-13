<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'mot-de-passe-test';

    public function test_l_ecran_de_connexion_s_affiche(): void
    {
        $this->get('/login')->assertOk()->assertSee('Se connecter');
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_un_utilisateur_se_connecte_avec_ses_identifiants(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::Login->value, 'user_id' => $user->id]);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse_et_trace(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);

        $this->assertGuest();

        $log = AuditLog::where('action', AuditAction::LoginFailed->value)->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertStringContainsString($user->email, $log->description);
        $this->assertStringNotContainsString('mauvais-mot-de-passe', json_encode($log->toArray()));
    }

    public function test_le_message_est_le_meme_pour_un_compte_inconnu(): void
    {
        $this->post('/login', ['email' => 'personne@example.com', 'password' => 'peu-importe-lequel'])
            ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_la_connexion_est_bloquee_apres_cinq_echecs(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe']);
        }

        // Même avec le bon mot de passe, la sixième tentative est refusée.
        $response = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_un_compte_desactive_en_cours_de_session_est_deconnecte(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_la_deconnexion_ferme_la_session_et_est_tracee(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::Logout->value, 'user_id' => $user->id]);
    }

    public function test_un_mot_de_passe_attribue_doit_etre_change_avant_toute_autre_page(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.edit'));
        $this->get('/password')->assertOk()->assertSee('Choisissez votre propre mot de passe');
    }

    public function test_un_utilisateur_change_son_mot_de_passe(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put('/password', [
            'current_password' => self::PASSWORD,
            'password' => 'une phrase assez longue',
        ])->assertRedirect(route('home'));

        $user->refresh();
        $this->assertTrue(Hash::check('une phrase assez longue', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_un_nouveau_mot_de_passe_de_moins_de_douze_caracteres_est_refuse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => self::PASSWORD,
            'password' => 'trop-court',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_le_changement_exige_le_mot_de_passe_actuel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'pas-le-bon-du-tout',
            'password' => 'une phrase assez longue',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_les_reponses_portent_les_en_tetes_de_securite(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function test_la_page_des_mentions_legales_est_publique(): void
    {
        $this->get('/legal')->assertOk()->assertSee('2013-450');
    }
}
