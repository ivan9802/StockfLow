<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Proxies de confiance, lus dans la configuration à chaque requête.
 *
 * Derrière le proxy inverse TLS de production, l'adresse du client et le schéma
 * HTTPS arrivent dans les en-têtes X-Forwarded-*. Sans cela, la limitation des
 * connexions compterait toute l'entreprise comme une seule adresse et HSTS ne
 * serait jamais envoyé.
 *
 * La valeur vient de config('stockflow.trusted_proxies') : « * », une liste
 * d'adresses séparées par des virgules, ou vide (aucun proxy, développement).
 * Lue au moment de la requête, elle fonctionne avec un fichier .env comme avec
 * la configuration mise en cache (config:cache).
 */
class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    protected function proxies()
    {
        $proxies = trim((string) config('stockflow.trusted_proxies'));

        if ($proxies === '') {
            return null;
        }

        return $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies))));
    }
}
