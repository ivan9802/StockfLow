<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · StockFlow</title>
    @vite(['resources/scss/print.scss', 'resources/js/print.js'])
</head>
<body>
    <div class="print-toolbar no-print">
        <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer" aria-hidden="true"></i> Imprimer</button>
        <a class="btn btn-outline-secondary" href="@yield('back')">Retour</a>
    </div>

    <main class="sheet">
        @php $company = config('stockflow.company'); @endphp
        <header class="sheet-head">
            <div>
                <div class="company">{{ $company['name'] }}</div>
                <div class="muted">{{ $company['address'] }}@if ($company['phone']) · {{ $company['phone'] }}@endif</div>
                <div class="muted">RCCM : {{ $company['rccm'] }}</div>
            </div>
            <div class="doc">
                <div class="doc-title">@yield('doc-title')</div>
                <div class="doc-ref">@yield('doc-ref')</div>
                <div class="muted">@yield('doc-date')</div>
            </div>
        </header>

        @yield('content')

        <footer class="sheet-foot muted">
            Document édité par StockFlow le {{ now()->format('d/m/Y à H:i') }}.
        </footer>
    </main>
</body>
</html>
