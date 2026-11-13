<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Tableau de bord') · StockFlow</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <div class="app-shell">
        @include('layouts.partials.sidebar')
        <div class="sidebar-backdrop" data-sidebar-close hidden></div>

        <div class="app-main">
            @include('layouts.partials.topbar')

            <main id="contenu" class="app-content" tabindex="-1">
                @if (session('error'))
                    <x-alert type="danger" :message="session('error')" />
                @endif
                @if (session('warning'))
                    <x-alert type="warning" :message="session('warning')" />
                @endif

                @yield('content')
            </main>

            <footer class="app-footer">
                StockFlow · <a href="{{ route('legal') }}">Mentions légales et données personnelles</a>
            </footer>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" aria-live="polite" aria-atomic="true">
        @if (session('success'))
            <x-toast :message="session('success')" />
        @endif
    </div>

    <x-confirm-modal />
</body>
</html>
