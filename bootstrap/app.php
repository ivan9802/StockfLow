<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En-têtes de sécurité sur toutes les réponses (SEC-21) : pages, erreurs rendues
        // hors du groupe web (404, 503 de maintenance), /up et réponses JSON. Placé en tête
        // de la pile globale, il enveloppe tous les autres middlewares.
        $middleware->prepend(SecurityHeaders::class);

        // Proxies de confiance lus dans config('stockflow.trusted_proxies') à chaque
        // requête. Cette fonction s'exécute avant le chargement du fichier .env :
        // un env() ici ne verrait que les variables du processus.
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('internal/*') || $request->expectsJson(),
        );

        // Une règle métier refusée (stock insuffisant, transition interdite, motif manquant)
        // n'est pas une panne : on la rend à l'utilisateur avec son message en français.
        $exceptions->dontReport(BusinessException::class);

        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->is('internal/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()] + $e->details(), 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
