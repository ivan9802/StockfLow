<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité posés sur chaque réponse.
 *
 * La politique de contenu n'autorise que les scripts et les styles servis par
 * l'application : aucun script en ligne dans les vues, aucun CDN tiers.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $nonce = Vite::cspNonce();
        $self = "'self'";
        $script = [$self, "'nonce-$nonce'"];
        $style = [$self, "'nonce-$nonce'"];
        $connect = [$self];

        // Serveur de développement de Vite (npm run dev) : scripts et rechargement à chaud.
        if (app()->environment('local') && Vite::isRunningHot()) {
            $dev = 'http://localhost:'.env('VITE_PORT', 5173);
            $script[] = $dev;
            $style[] = $dev;
            $style[] = "'unsafe-inline'";
            $connect[] = $dev;
            $connect[] = 'ws://localhost:'.env('VITE_PORT', 5173);
        }

        $policy = implode('; ', [
            "default-src $self",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src $self data: blob:",
            "font-src $self",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri $self",
            "form-action $self",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
