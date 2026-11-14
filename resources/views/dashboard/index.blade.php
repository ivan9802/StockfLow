@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-page-header title="Tableau de bord">
        <x-slot:context>
            <span data-dashboard-refresh="{{ route('internal.dashboard') }}" data-interval="{{ $refreshSeconds }}">
                Données en direct, actualisées à <span data-updated-at>{{ now()->format('H:i:s') }}</span>
            </span>
        </x-slot:context>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="Valeur du stock" key="stock_value" :value="Format::money($kpis['stock_value'])" hint="Au prix d'achat" :href="route('stock.index')" />
        </div>
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="Références actives" key="active_products" :value="Format::number($kpis['active_products'])" :href="route('products.index', ['state' => 'active'])" />
        </div>
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="Sous le seuil" key="low_count" :value="Format::number($kpis['low_count'])" hint="À réapprovisionner"
                :href="route('products.index', ['stock' => 'low'])" :tone="$kpis['low_count'] > 0 ? 'warning' : null" />
        </div>
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="En rupture" key="out_count" :value="Format::number($kpis['out_count'])" hint="Stock total à zéro"
                :href="route('products.index', ['stock' => 'out'])" :tone="$kpis['out_count'] > 0 ? 'danger' : null" />
        </div>
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="Mouvements du jour" key="movements_today" :value="Format::number($kpis['movements_today'])"
                :href="route('movements.index', ['from' => today()->format('Y-m-d')])" />
        </div>
        <div class="col-6 col-lg-4 col-xxl-2">
            <x-kpi-card label="Ventes du jour" key="sales_today_amount" :value="Format::money($kpis['sales_today_amount'])"
                :hint="$kpis['sales_today_count'].' vente'.($kpis['sales_today_count'] > 1 ? 's' : '')" :href="route('sales.index', ['from' => today()->format('Y-m-d')])" />
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="table-card mb-4">
                <div class="card-header px-3 py-2">
                    <h2>À réapprovisionner</h2>
                    <a href="{{ route('alerts.index') }}">Toutes les alertes</a>
                </div>
                @if ($toReorder->isEmpty())
                    <x-empty-state icon="check-circle" title="Aucun produit sous son seuil." />
                @else
                    <table class="table data-table">
                        <caption class="visually-hidden">Produits sous le seuil d'alerte</caption>
                        <thead><tr><th scope="col">Produit</th><th scope="col" class="num">Stock</th><th scope="col" class="num col-p2">Seuil</th><th scope="col" class="col-p3">Fournisseur</th><th scope="col">État</th></tr></thead>
                        <tbody>
                            @foreach ($toReorder as $product)
                                <tr>
                                    <td><a class="row-title" href="{{ route('products.show', $product) }}">{{ $product->name }}</a><span class="row-sub code">{{ $product->sku }}</span></td>
                                    <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                                    <td class="num col-p2">{{ Format::number($product->alert_threshold) }}</td>
                                    <td class="col-p3">{{ $product->supplier?->name }}</td>
                                    <td><x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="table-card">
                <div class="card-header px-3 py-2">
                    <h2>Dernières opérations</h2>
                    <a href="{{ route('movements.index') }}">Tout l'historique</a>
                </div>
                @if ($movements->isEmpty())
                    <x-empty-state icon="arrow-left-right" title="Aucun mouvement enregistré." />
                @else
                    @include('movements._rows', ['movements' => $movements, 'showProduct' => true])
                @endif
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card mb-4">
                <div class="card-header"><h2>Répartition par emplacement</h2></div>
                <div class="card-body">
                    <div class="bar-list">
                        @foreach ($byWarehouse as $row)
                            <div class="bar-row">
                                <div class="bar-label">
                                    <span class="fw-semibold">{{ $row['name'] }}</span>
                                    <span class="tabular">{{ Format::number($row['units']) }} unités · {{ $row['percent'] }} %</span>
                                </div>
                                <progress max="100" value="{{ $row['percent'] }}" aria-label="{{ $row['name'] }} : {{ $row['percent'] }} % des unités"></progress>
                                <span class="small text-secondary tabular">{{ Format::money($row['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2>Achats</h2></div>
                <div class="card-body">
                    <p class="mb-2"><span class="fw-semibold tabular" data-kpi="pending_orders">{{ $kpis['pending_orders'] }}</span> commande(s) en attente de livraison.</p>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('purchase-orders.index', ['status' => 'pending']) }}">Voir les commandes</a>
                </div>
            </section>
        </div>
    </div>
@endsection
