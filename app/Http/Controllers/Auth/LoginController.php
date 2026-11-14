<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Nombre d'échecs tolérés par minute pour un couple e-mail et adresse IP. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly AuditService $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            // Un blocage signale une tentative de deviner un mot de passe : il est tracé.
            $this->audit->record(AuditAction::LoginFailed, null,
                'Connexion bloquée après trop d\'échecs pour '.$request->string('email'));

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans $minutes minute".($minutes > 1 ? 's' : '').'.',
            ]);
        }

        $credentials = $request->only('email', 'password') + ['is_active' => true];

        // Pas d'option « rester connecté » : les postes du comptoir et de l'entrepôt sont
        // partagés, et chaque opération doit rester attribuée à la bonne personne.
        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, 60);

            // On trace l'adresse essayée, jamais le mot de passe.
            $this->audit->record(
                AuditAction::LoginFailed, null,
                'Connexion refusée pour '.$request->string('email'),
            );

            // Même message pour un compte inconnu, désactivé ou un mauvais mot de passe :
            // la réponse ne révèle pas si l'adresse existe.
            throw ValidationException::withMessages([
                'email' => 'Adresse e-mail ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($key);

        // Nouvel identifiant de session à la connexion (contre la fixation de session).
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record(AuditAction::Login, $user, 'Connexion de '.$user->name);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $this->audit->record(AuditAction::Logout, $user, 'Déconnexion de '.$user->name);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
