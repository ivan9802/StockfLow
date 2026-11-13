<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session d'un compte désactivé par l'administrateur.
 * Sans ce contrôle, un utilisateur déjà connecté garderait son accès
 * jusqu'à l'expiration de sa session.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ce compte a été désactivé.'], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte a été désactivé. Adressez-vous à votre administrateur.',
            ]);
        }

        return $next($request);
    }
}
