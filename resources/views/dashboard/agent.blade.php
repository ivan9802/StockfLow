@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-page-header title="Tableau de bord">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('sales.create') }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Nouvelle vente</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <x-kpi-card label="Mes ventes du jour" :value="Format::money($kpis['sales_today_amount'])"
                :hint="$kpis['sales_today_count'].' vente'.($kpis['sales_today_count'] > 1 ? 's' : '')" :href="route('sales.index', ['from' => today()->format('Y-m-d')])" />
        </div>
        <div class="col-sm-4">
            <x-kpi-card label="Produits en rupture" :value="Format::number($kpis['out_count'])" :href="route('stock.index', ['stock' => 'out'])"
                :tone="$kpis['out_count'] > 0 ? 'danger' : null" />
        </div>
        <div class="col-sm-4">
            <x-kpi-card label="Produits sous le seuil" :value="Format::number($kpis['low_count'])" :href="route('stock.index', ['stock' => 'low'])"
                :tone="$kpis['low_count'] > 0 ? 'warning' : null" />
        </div>
    </div>

    <section class="table-card">
        <div class="card-header px-3 py-2"><h2>Mes dernières ventes</h2><a href="{{ route('sales.index') }}">Toutes mes ventes</a></div>
        @if ($latestSales->isEmpty())
            <x-empty-state icon="receipt" title="Aucune vente pour l'instant." />
        @else
            <table class="table data-table">
                <thead><tr><th scope="col">Bon</th><th scope="col">Date</th><th scope="col" class="num">Montant</th><th scope="col">Statut</th></tr></thead>
                <tbody>
                    @foreach ($latestSales as $sale)
                        <tr>
                            <td><a class="row-title code" href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                            <td>{{ Format::dateTime($sale->sold_at) }}</td>
                            <td class="num">{{ Format::money($sale->total_amount) }}</td>
                            <td><x-status-badge :status="$sale->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
