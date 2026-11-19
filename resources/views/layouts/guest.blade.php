<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Connexion') · StockFlow</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    @hasSection('aside')
        {{-- Mise en page en deux colonnes (connexion) : présentation à gauche, formulaire à droite. --}}
        <main class="guest-shell is-login">
            <aside class="login-aside">@yield('aside')</aside>
            <div class="login-main">
                @yield('content')
                <p class="mt-4 mb-0 small text-secondary">
                    <a class="link-secondary" href="{{ route('legal') }}">Mentions légales et données personnelles</a>
                </p>
            </div>
        </main>
    @else
        <main class="guest-shell">
            <div class="guest-brand">
                <div class="name">Stock<span>Flow</span></div>
                <div class="company">{{ config('stockflow.company.name') }}</div>
            </div>

            @yield('content')

            <p class="mt-4 mb-0 small text-secondary">
                <a class="link-secondary" href="{{ route('legal') }}">Mentions légales et données personnelles</a>
            </p>
        </main>
    @endif
</body>
</html>
