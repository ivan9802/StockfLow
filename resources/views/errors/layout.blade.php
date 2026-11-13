<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · StockFlow</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/scss/app.scss'])
</head>
<body>
    <main class="guest-shell">
        <div class="guest-brand">
            <div class="name">Stock<span>Flow</span></div>
        </div>
        <div class="guest-card text-center">
            <p class="overline mb-1">Erreur @yield('code')</p>
            <h1 class="h2 mb-2">@yield('title')</h1>
            <p class="text-secondary">@yield('message')</p>
            <a class="btn btn-primary" href="{{ url('/') }}">Revenir à l'accueil</a>
        </div>
    </main>
</body>
</html>
