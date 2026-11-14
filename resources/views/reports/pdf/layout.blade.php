<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 13pt; margin: 0 0 2mm; }
        .muted { color: #555; }
        .head { border-bottom: 1.5px solid #111; padding-bottom: 3mm; margin-bottom: 4mm; }
        .head table { width: 100%; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 7.5pt; text-transform: uppercase; border-bottom: 1px solid #111; padding: 1.5mm 1mm; }
        table.lines td { border-bottom: 0.5px solid #bbb; padding: 1.2mm 1mm; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .code { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; }
        .total td { font-weight: bold; border-bottom: 0; border-top: 1px solid #111; }
        .low { color: #7a5200; font-weight: bold; }
        .out { color: #a32a1f; font-weight: bold; }
        .foot { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7pt; color: #555; text-align: center; }
    </style>
</head>
<body>
    @php $company = config('stockflow.company'); @endphp
    <div class="head">
        <table>
            <tr>
                <td><strong>{{ $company['name'] }}</strong><br><span class="muted">{{ $company['address'] }}</span></td>
                <td style="text-align: right"><h1>@yield('title')</h1><span class="muted">Édité le {{ now()->format('d/m/Y à H:i') }}</span></td>
            </tr>
        </table>
    </div>

    @yield('content')

    <div class="foot">StockFlow · {{ $company['name'] }} · document de contrôle interne</div>
</body>
</html>
