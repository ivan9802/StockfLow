<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        // Derrière le proxy inverse TLS de production, l'adresse du client et le schéma
        // HTTPS arrivent dans les en-têtes X-Forwarded-*. Sans cela, la limitation des
        // connexions compterait toute l'entreprise comme une seule adresse et HSTS ne
        // serait jamais envoyé. TRUSTED_PROXIES vaut « * » en production (le service web
        // n'écoute que sur 127.0.0.1) et reste vide en développement.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
                headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

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
