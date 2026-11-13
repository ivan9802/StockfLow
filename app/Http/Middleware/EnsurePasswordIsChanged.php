<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant qu'un utilisateur n'a pas remplacé le mot de passe que l'administrateur
 * lui a attribué, il ne peut ouvrir que l'écran de changement de mot de passe.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Vous devez d\'abord changer votre mot de passe.'], 403);
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
