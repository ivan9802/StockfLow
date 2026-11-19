<?php

// Réglages propres à StockFlow.

return [

    // Identité de l'entreprise, reprise sur l'écran de connexion, les bons imprimés
    // et la page des mentions légales. À renseigner dans .env pour chaque installation.
    'company' => [
        'name' => env('COMPANY_NAME', 'Ivoire Bureautique & Tech'),
        'address' => env('COMPANY_ADDRESS', 'Abidjan, Côte d\'Ivoire'),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', 'contact@example.com'),
        'rccm' => env('COMPANY_RCCM', 'à renseigner'),
        'publisher' => env('COMPANY_PUBLISHER', 'à renseigner'),
        'host' => env('COMPANY_HOST', 'à renseigner'),
    ],

    // Nombre de lignes par page dans les listes.
    'per_page' => 20,

    // Au-delà, un état PDF est refusé et l'export CSV est proposé à la place.
    'pdf_max_rows' => 2000,

    // Intervalle de rafraîchissement du tableau de bord, en secondes.
    'dashboard_refresh_seconds' => 30,

    // Images de produits.
    'product_image' => [
        'max_kilobytes' => 2048,
        'mimes' => ['jpeg', 'png', 'webp'],
    ],

    // Proxies de confiance (App\Http\Middleware\TrustProxies) : « * » derrière le proxy
    // inverse TLS de production, une liste d'adresses séparées par des virgules, ou vide
    // en développement (aucun en-tête X-Forwarded-* n'est alors pris en compte).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Port du serveur de développement de Vite, autorisé par la politique de contenu
    // (App\Http\Middleware\SecurityHeaders) en environnement local seulement.
    'vite_port' => (int) env('VITE_PORT', 5173),
];
